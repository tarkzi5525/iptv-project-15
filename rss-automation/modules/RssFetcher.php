<?php
/**
 * Module 1 – RSS Fetcher
 *
 * Reads the RSS/Atom feed and returns an array of raw article items.
 * Each item contains: title, link, pubDate, description (excerpt).
 */

class RssFetcher
{
    private string $feedUrl;
    private Logger $log;

    public function __construct(string $feedUrl, Logger $log)
    {
        $this->feedUrl = $feedUrl;
        $this->log     = $log;
    }

    /**
     * Fetch items from the RSS feed.
     *
     * @param  int   $limit  Maximum number of items to return (newest first).
     * @return array<array{title:string,link:string,pubDate:string,excerpt:string}>
     */
    public function fetch(int $limit = 10): array
    {
        $this->log->info("Fetching RSS feed: {$this->feedUrl}");

        $xml = $this->httpGet($this->feedUrl);
        if (!$xml) {
            $this->log->error("Failed to fetch RSS feed.");
            return [];
        }

        libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        if (!$doc) {
            $this->log->error("Failed to parse RSS XML.");
            return [];
        }

        $items = [];

        // Support both RSS 2.0 (<item>) and Atom (<entry>)
        $entries = $doc->channel->item ?? $doc->entry ?? [];

        foreach ($entries as $entry) {
            if (count($items) >= $limit) break;

            $link = (string)($entry->link ?? $entry->link['href'] ?? '');
            // Atom feeds sometimes use <link href="..."/>
            if (empty($link) && isset($entry->link)) {
                $attrs = $entry->link->attributes();
                $link  = (string)($attrs['href'] ?? '');
            }

            $items[] = [
                'title'   => trim((string)($entry->title ?? '')),
                'link'    => trim($link),
                'pubDate' => trim((string)($entry->pubDate ?? $entry->published ?? date('Y-m-d'))),
                'excerpt' => trim(strip_tags((string)($entry->description ?? $entry->summary ?? ''))),
            ];
        }

        $this->log->info("Fetched " . count($items) . " items from RSS.");
        return $items;
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function httpGet(string $url): string|false
    {
        $ctx = stream_context_create([
            'http' => [
                'method'  => 'GET',
                'timeout' => 15,
                'header'  => "User-Agent: RSS-Automation-Bot/1.0\r\n",
            ],
            'ssl' => ['verify_peer' => false],
        ]);
        return @file_get_contents($url, false, $ctx);
    }
}
