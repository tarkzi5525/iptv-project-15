<?php
/**
 * Module 2 – Content Extractor
 *
 * Visits the original article URL and extracts clean plain-text body content.
 * Mimics the n8n HTTP Request + HTML Extract + Clean Data nodes.
 */

class ContentExtractor
{
    private Logger $log;

    public function __construct(Logger $log)
    {
        $this->log = $log;
    }

    /**
     * Fetch and extract the full article text from a URL.
     *
     * @return array{html:string, text:string, wordCount:int}
     */
    public function extract(string $url): array
    {
        $this->log->info("Extracting content from: $url");

        $html = $this->httpGet($url);
        if (!$html) {
            $this->log->error("Could not fetch URL: $url");
            return ['html' => '', 'text' => '', 'wordCount' => 0];
        }

        $text = $this->htmlToText($html);

        return [
            'html'      => $html,
            'text'      => $text,
            'wordCount' => str_word_count($text),
        ];
    }

    // ─── HTML → plain text ───────────────────────────────────────────────────

    private function htmlToText(string $html): string
    {
        // Remove scripts, styles, nav, footer, header, ads
        $html = preg_replace(
            '/<(script|style|nav|footer|header|aside|form|iframe|noscript)[^>]*>.*?<\/\1>/si',
            '',
            $html
        );

        // Try to isolate the article body (common selectors)
        foreach (['<article', '<main', 'class="post-content"', 'class="article-body"',
                   'class="entry-content"', 'id="content"'] as $marker) {
            $pos = stripos($html, $marker);
            if ($pos !== false) {
                $html = substr($html, $pos);
                break;
            }
        }

        // Convert block-level tags to newlines
        $html = preg_replace('/<(p|br|div|li|h[1-6]|blockquote)[^>]*>/i', "\n", $html);

        // Strip all remaining tags
        $text = strip_tags($html);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Collapse whitespace
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    // ─── HTTP helper ─────────────────────────────────────────────────────────

    private function httpGet(string $url): string|false
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 20,
                'header'  =>
                    "User-Agent: Mozilla/5.0 (compatible; RSS-Automation/1.0)\r\n" .
                    "Accept: text/html,application/xhtml+xml\r\n" .
                    "Accept-Language: en-US,en;q=0.9\r\n",
            ],
            'ssl' => ['verify_peer' => false],
        ]);
        return @file_get_contents($url, false, $ctx);
    }
}
