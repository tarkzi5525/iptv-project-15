<?php
/**
 * Module 3 – Meta Analyzer
 *
 * Generates an SEO-optimised title and meta description from the article text.
 * Mirrors the "Analyze Meta Title" and "Analyze Meta Description" groups in n8n.
 */

class MetaAnalyzer
{
    private AiClient $ai;
    private string   $lang;
    private string   $niche;
    private Logger   $log;

    public function __construct(AiClient $ai, array $config, Logger $log)
    {
        $this->ai    = $ai;
        $this->lang  = $config['target_language'] ?? 'en';
        $this->niche = $config['site_niche']       ?? '';
        $this->log   = $log;
    }

    /**
     * @return array{seoTitle:string, metaDescription:string}
     */
    public function analyze(string $originalTitle, string $articleText): array
    {
        $this->log->info("Analyzing meta for: $originalTitle");

        $truncated = mb_substr($articleText, 0, 3000);
        $langLabel = $this->lang === 'ar' ? 'Arabic' : 'English';

        // ── SEO Title ─────────────────────────────────────────────────────────
        $seoTitle = $this->ai->chat(
            "You are an SEO copywriter specialising in {$this->niche}. " .
            "Write only the SEO title – no quotes, no explanations. " .
            "Language: {$langLabel}. " .
            "Requirements: max 60 characters, include a power word, be compelling.",
            "Original title: {$originalTitle}\n\nArticle excerpt:\n{$truncated}",
            80
        );

        // ── Meta Description ──────────────────────────────────────────────────
        $metaDesc = $this->ai->chat(
            "You are an SEO copywriter specialising in {$this->niche}. " .
            "Write only the meta description – no quotes, no explanations. " .
            "Language: {$langLabel}. " .
            "Requirements: 140-160 characters, include a call-to-action, be informative.",
            "Article title: {$seoTitle}\n\nArticle excerpt:\n{$truncated}",
            120
        );

        $this->log->info("Meta generated – title: " . mb_substr($seoTitle, 0, 60));

        return [
            'seoTitle'        => trim($seoTitle),
            'metaDescription' => trim($metaDesc),
        ];
    }
}
