<?php
/**
 * Module 5 – Article Rewriter
 *
 * Takes the original article text, the SEO meta, and keyword/competitor data,
 * then uses the AI to produce a completely rewritten, SEO-optimised article.
 *
 * Mirrors the "Rewrite Article" group in n8n.
 */

class ArticleRewriter
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
     * @param array $meta      {seoTitle, metaDescription}
     * @param array $research  {keywords, competitorTitles, strategy}
     * @return array{title:string, content:string, excerpt:string, readTime:string}
     */
    public function rewrite(string $originalText, array $meta, array $research): array
    {
        $this->log->info("Rewriting article: {$meta['seoTitle']}");

        $langLabel   = $this->lang === 'ar' ? 'Arabic' : 'English';
        $keywords    = implode(', ', array_slice($research['keywords'] ?? [], 0, 10));
        $strategy    = $research['strategy'] ?? '';
        $truncated   = mb_substr($originalText, 0, 4000);

        $systemPrompt = <<<PROMPT
You are a professional journalist and SEO content writer specialising in {$this->niche}.

Your task: Rewrite the provided article into a NEW, high-quality article.

Rules:
1. Write in {$langLabel}.
2. Use HTML formatting: <h2>, <h3>, <p>, <ul>, <li>, <strong> – NO markdown.
3. Length: 600–900 words.
4. Naturally integrate these keywords (do NOT stuff): {$keywords}
5. Follow this editorial strategy: {$strategy}
6. The tone must be engaging, journalistic, and reader-friendly.
7. Do NOT copy sentences verbatim from the original – fully rephrase.
8. Start with a compelling introduction paragraph.
9. Include at least 2 subheadings (h2).
10. End with a conclusion paragraph.

Return ONLY the HTML article body – no <html>, <head>, or <body> tags.
PROMPT;

        $content = $this->ai->chat($systemPrompt, $truncated, 1800);

        // ── Generate a short excerpt ──────────────────────────────────────────
        $excerptPrompt = $this->lang === 'ar'
            ? "اكتب ملخصاً جذاباً من جملتين للمقال التالي باللغة العربية:"
            : "Write a compelling 2-sentence excerpt for this article in English:";

        $excerpt = $this->ai->chat(
            "You summarise articles. Return only the excerpt text.",
            $excerptPrompt . "\n\n" . strip_tags($content),
            120
        );

        // ── Estimate reading time ─────────────────────────────────────────────
        $wordCount = str_word_count(strip_tags($content));
        $minutes   = max(1, (int) round($wordCount / 200));
        $readTime  = $this->lang === 'ar' ? "{$minutes} دقائق" : "{$minutes} min read";

        $this->log->info("Article rewritten – ~{$wordCount} words, {$readTime}.");

        return [
            'title'    => $meta['seoTitle'],
            'content'  => trim($content),
            'excerpt'  => trim($excerpt),
            'readTime' => $readTime,
        ];
    }
}
