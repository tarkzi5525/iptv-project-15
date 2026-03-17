<?php
/**
 * RSS Article Automation - Configuration
 *
 * Fill in your API keys and RSS feed URL before running.
 * You can also set these as environment variables for security.
 */

return [

    // ─── RSS SOURCE ────────────────────────────────────────────────────────────
    'rss_feed_url' => getenv('RSS_FEED_URL') ?: '',
    // Example: 'https://feeds.feedburner.com/TechCrunch'

    // ─── OPENAI / ANTHROPIC API ────────────────────────────────────────────────
    'ai_provider'  => getenv('AI_PROVIDER') ?: 'openai',   // 'openai' or 'anthropic'
    'ai_api_key'   => getenv('AI_API_KEY')  ?: '',
    'ai_model'     => getenv('AI_MODEL')    ?: 'gpt-4o-mini',
    // Anthropic models: 'claude-opus-4-6', 'claude-sonnet-4-6', 'claude-haiku-4-5-20251001'
    // OpenAI models   : 'gpt-4o', 'gpt-4o-mini', 'gpt-3.5-turbo'

    // ─── SERP / KEYWORD RESEARCH ───────────────────────────────────────────────
    'serp_api_key' => getenv('SERP_API_KEY') ?: '',
    // Get a free key at https://serpapi.com

    // ─── DATABASE (blog.json) ──────────────────────────────────────────────────
    'blog_json_path' => __DIR__ . '/../data/blog.json',

    // ─── BEHAVIOUR ────────────────────────────────────────────────────────────
    'max_articles_per_run' => (int)(getenv('MAX_ARTICLES') ?: 3),
    // How many new articles to process each time the script runs

    'target_language' => getenv('TARGET_LANG') ?: 'ar',
    // 'ar' = Arabic, 'en' = English

    'site_niche' => 'IPTV streaming services',
    // Describe your site niche so AI writes relevant content

    // ─── LOGGING ──────────────────────────────────────────────────────────────
    'log_file'  => __DIR__ . '/logs/automation.log',
    'log_level' => 'info',   // 'debug' | 'info' | 'error'

];
