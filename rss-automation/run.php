#!/usr/bin/env php
<?php
/**
 * ╔══════════════════════════════════════════════════════════════╗
 * ║          RSS Article Automation – Main Orchestrator          ║
 * ║                                                              ║
 * ║  Workflow stages (mirrors the n8n diagram):                  ║
 * ║   1. Fetch RSS feed                                          ║
 * ║   2. Skip already-processed articles                         ║
 * ║   3. Extract full article content                            ║
 * ║   4. Analyse / generate SEO meta title & description         ║
 * ║   5. Competitor research + keyword extraction (SerpAPI + AI) ║
 * ║   6. Rewrite article with AI                                 ║
 * ║   7. Save to blog.json database                              ║
 * ╚══════════════════════════════════════════════════════════════╝
 *
 * Usage:
 *   php run.php
 *   RSS_FEED_URL=https://... AI_API_KEY=sk-... php run.php
 *
 * Or set values directly in config.php.
 */

declare(strict_types=1);

// ─── Bootstrap ───────────────────────────────────────────────────────────────

$moduleDir = __DIR__ . '/modules';
foreach (['Logger', 'RssFetcher', 'ContentExtractor', 'AiClient',
          'MetaAnalyzer', 'KeywordResearcher', 'ArticleRewriter', 'DatabaseUpdater'] as $cls) {
    require_once $moduleDir . "/{$cls}.php";
}

$config = require __DIR__ . '/config.php';

// ─── Init services ────────────────────────────────────────────────────────────

$log = new Logger($config['log_file'], $config['log_level']);

$log->info("════════════════════════════════════════");
$log->info("  RSS Article Automation – START");
$log->info("════════════════════════════════════════");

// Validate required config
if (empty($config['rss_feed_url'])) {
    $log->error("rss_feed_url is not set. Edit config.php or set RSS_FEED_URL env var.");
    exit(1);
}
if (empty($config['ai_api_key'])) {
    $log->error("ai_api_key is not set. Edit config.php or set AI_API_KEY env var.");
    exit(1);
}

// Instantiate modules
$fetcher    = new RssFetcher($config['rss_feed_url'], $log);
$extractor  = new ContentExtractor($log);
$aiClient   = new AiClient($config, $log);
$metaAnal   = new MetaAnalyzer($aiClient, $config, $log);
$keywords   = new KeywordResearcher($aiClient, $config, $log);
$rewriter   = new ArticleRewriter($aiClient, $config, $log);
$db         = new DatabaseUpdater(
    $config['blog_json_path'],
    $log,
    $config['n8n_webhook_url']   ?? '',
    $config['n8n_webhook_token'] ?? ''
);

// ─── Stage 1: Fetch RSS ───────────────────────────────────────────────────────

$items = $fetcher->fetch(20);   // fetch up to 20, we'll filter below

if (empty($items)) {
    $log->info("No items found in the RSS feed. Exiting.");
    exit(0);
}

// ─── Stage 2: Filter duplicates ───────────────────────────────────────────────

$newItems = [];
foreach ($items as $item) {
    if ($db->exists($item['link'], $item['title'])) {
        $log->info("Skip (already in DB): {$item['title']}");
        continue;
    }
    $newItems[] = $item;
    if (count($newItems) >= $config['max_articles_per_run']) break;
}

if (empty($newItems)) {
    $log->info("All recent items already processed. Nothing to do.");
    exit(0);
}

$log->info(count($newItems) . " new article(s) to process.");

// ─── Stages 3-7: Process each article ────────────────────────────────────────

$processed = 0;

foreach ($newItems as $item) {
    $log->info("─── Processing: {$item['title']}");

    // Stage 3 – Full content
    $extracted = $extractor->extract($item['link']);
    if (empty($extracted['text'])) {
        $log->error("Could not extract content – skipping.");
        continue;
    }

    // Stage 4 – Meta analysis
    $meta = $metaAnal->analyze($item['title'], $extracted['text']);

    // Stage 5 – Competitor + keyword research
    $research = $keywords->research($item['title']);

    // Stage 6 – Rewrite
    $article = $rewriter->rewrite($extracted['text'], $meta, $research);

    if (empty($article['content'])) {
        $log->error("AI returned empty content – skipping.");
        continue;
    }

    // Stage 7 – Save to DB
    $saved = $db->insert(
        article:   $article,
        meta:      $meta,
        research:  $research,
        sourceUrl: $item['link'],
        category:  detectCategory($item['title'], $config['site_niche'])
    );

    $log->info("Saved – slug: {$saved['slug']}");
    $processed++;
}

$log->info("════════════════════════════════════════");
$log->info("  Done. Processed {$processed} article(s).");
$log->info("════════════════════════════════════════");
exit(0);

// ─── Helper ──────────────────────────────────────────────────────────────────

/**
 * Simple rule-based category detector.
 * Extend this list to match your site's categories.
 */
function detectCategory(string $title, string $niche): string
{
    $lower = strtolower($title);
    $map   = [
        'review'    => 'Reviews',
        'guide'     => 'Guides',
        'how to'    => 'Guides',
        'setup'     => 'Guides',
        'sport'     => 'Sports',
        'football'  => 'Sports',
        'movie'     => 'Entertainment',
        'stream'    => 'Technology',
        'buffer'    => 'Technology',
        '4k'        => 'Technology',
        'firestick' => 'Guides',
        'android'   => 'Guides',
    ];
    foreach ($map as $keyword => $cat) {
        if (str_contains($lower, $keyword)) return $cat;
    }
    return 'News';
}
