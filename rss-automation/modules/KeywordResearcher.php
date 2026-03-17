<?php
/**
 * Module 4 – Keyword & Competitor Researcher
 *
 * 1. Uses SerpAPI to search Google for the topic and gather competitor titles/snippets.
 * 2. Passes those results to the AI to extract the best keywords and a content strategy.
 *
 * Mirrors the "competitor_analysis" and "Keyword_Research" groups in n8n.
 */

class KeywordResearcher
{
    private AiClient $ai;
    private string   $serpApiKey;
    private string   $lang;
    private string   $niche;
    private Logger   $log;

    public function __construct(AiClient $ai, array $config, Logger $log)
    {
        $this->ai         = $ai;
        $this->serpApiKey = $config['serp_api_key']    ?? '';
        $this->lang       = $config['target_language'] ?? 'en';
        $this->niche      = $config['site_niche']       ?? '';
        $this->log        = $log;
    }

    /**
     * @return array{keywords:string[], competitorTitles:string[], strategy:string}
     */
    public function research(string $topic): array
    {
        $this->log->info("Researching keywords for: $topic");

        // ── Step 1: SERP results ──────────────────────────────────────────────
        $serpResults = $this->fetchSerp($topic);

        // ── Step 2: AI extracts keywords + strategy ───────────────────────────
        $langLabel = $this->lang === 'ar' ? 'Arabic' : 'English';

        $serpText = '';
        $competitorTitles = [];
        foreach (array_slice($serpResults, 0, 8) as $i => $r) {
            $competitorTitles[] = $r['title'];
            $serpText .= ($i + 1) . ". Title: {$r['title']}\n   Snippet: {$r['snippet']}\n\n";
        }

        $keywordJson = $this->ai->chat(
            "You are an SEO strategist specialising in {$this->niche}. " .
            "Analyse the competitor results and return ONLY a valid JSON object with two keys:\n" .
            "  \"keywords\": array of 8-12 target keywords (mix of short and long-tail)\n" .
            "  \"strategy\": one paragraph describing what angle to use in the new article\n" .
            "Language for the strategy paragraph: {$langLabel}.",
            "Topic: {$topic}\n\nCompetitor SERP results:\n{$serpText}",
            600
        );

        $parsed = json_decode($keywordJson, true);
        $keywords = $parsed['keywords'] ?? $this->extractKeywordsFallback($topic);
        $strategy = $parsed['strategy'] ?? '';

        $this->log->info("Keywords found: " . implode(', ', array_slice($keywords, 0, 5)) . "...");

        return [
            'keywords'         => $keywords,
            'competitorTitles' => $competitorTitles,
            'strategy'         => $strategy,
        ];
    }

    // ─── SerpAPI call ────────────────────────────────────────────────────────

    private function fetchSerp(string $query): array
    {
        if (empty($this->serpApiKey)) {
            $this->log->info("No SerpAPI key – skipping competitor research.");
            return [];
        }

        $url = 'https://serpapi.com/search.json?' . http_build_query([
            'q'       => $query,
            'hl'      => 'en',
            'gl'      => 'us',
            'num'     => 10,
            'api_key' => $this->serpApiKey,
        ]);

        $ctx = stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 15],
            'ssl'  => ['verify_peer' => false],
        ]);
        $body = @file_get_contents($url, false, $ctx);
        if (!$body) return [];

        $data = json_decode($body, true);
        $results = [];
        foreach ($data['organic_results'] ?? [] as $r) {
            $results[] = [
                'title'   => $r['title']   ?? '',
                'snippet' => $r['snippet'] ?? '',
                'link'    => $r['link']    ?? '',
            ];
        }
        return $results;
    }

    // ─── Fallback when no SERP key ────────────────────────────────────────────

    private function extractKeywordsFallback(string $topic): array
    {
        // Ask the AI to generate keywords purely from the topic
        $raw = $this->ai->chat(
            "You are an SEO keyword expert for {$this->niche}.",
            "Generate 8 target keywords for the topic: \"{$topic}\". " .
            "Return only a JSON array of strings, nothing else.",
            200
        );
        $parsed = json_decode($raw, true);
        return is_array($parsed) ? $parsed : [$topic];
    }
}
