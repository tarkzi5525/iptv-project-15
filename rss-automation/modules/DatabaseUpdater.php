<?php
/**
 * Module 6 – Database Updater
 *
 * Reads and writes the blog.json file that powers the site.
 * Mirrors the "Update Database" group (NocoDB nodes) in n8n.
 */

class DatabaseUpdater
{
    private string $jsonPath;
    private Logger $log;

    public function __construct(string $jsonPath, Logger $log)
    {
        $this->jsonPath = $jsonPath;
        $this->log      = $log;
    }

    /**
     * Check if an article with this sourceUrl or title already exists.
     */
    public function exists(string $sourceUrl, string $title): bool
    {
        $db = $this->load();
        foreach ($db['posts'] ?? [] as $post) {
            if (
                (isset($post['sourceUrl']) && $post['sourceUrl'] === $sourceUrl) ||
                (isset($post['title'])     && strtolower($post['title']) === strtolower($title))
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Insert a new article into blog.json.
     *
     * @param array $article {title, content, excerpt, readTime}
     * @param array $meta    {seoTitle, metaDescription}
     * @param array $research {keywords}
     * @param string $sourceUrl  Original article URL
     * @param string $category
     */
    public function insert(
        array  $article,
        array  $meta,
        array  $research,
        string $sourceUrl,
        string $category = 'News'
    ): array {
        $db   = $this->load();
        $posts = $db['posts'] ?? [];

        // Build a URL-safe slug from the title
        $slug = $this->slugify($article['title']);

        // Ensure unique slug
        $existing = array_column($posts, 'slug');
        $base = $slug;
        $i    = 1;
        while (in_array($slug, $existing)) {
            $slug = $base . '-' . $i++;
        }

        // Determine next ID
        $ids    = array_filter(array_column($posts, 'id'), 'is_numeric');
        $nextId = $ids ? (string)(max($ids) + 1) : '1';

        $newPost = [
            'id'              => $nextId,
            'slug'            => $slug,
            'title'           => $article['title'],
            'metaTitle'       => $meta['seoTitle'],
            'metaDescription' => $meta['metaDescription'],
            'excerpt'         => $article['excerpt'],
            'content'         => $article['content'],
            'category'        => $category,
            'date'            => date('Y-m-d'),
            'readTime'        => $article['readTime'],
            'keywords'        => $research['keywords'] ?? [],
            'sourceUrl'       => $sourceUrl,
            'status'          => 'published',
        ];

        // Prepend (newest first)
        array_unshift($posts, $newPost);
        $db['posts'] = $posts;

        $this->save($db);

        $this->log->info("Article saved to DB – ID: {$nextId}, slug: {$slug}");
        return $newPost;
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function load(): array
    {
        if (!file_exists($this->jsonPath)) {
            return ['posts' => []];
        }
        $content = file_get_contents($this->jsonPath);
        return json_decode($content, true) ?? ['posts' => []];
    }

    private function save(array $data): void
    {
        file_put_contents(
            $this->jsonPath,
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
    }

    private function slugify(string $text): string
    {
        // Transliterate Arabic/non-ASCII to ASCII (best-effort)
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text) ?: $text;
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', trim($text));
        return trim($text, '-') ?: 'article-' . time();
    }
}
