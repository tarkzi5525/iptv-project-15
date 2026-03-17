# RSS Article Automation – Setup Guide

## What this system does

Automatically fetches articles from an RSS feed, rewrites them with AI (SEO-optimised), and saves them to `data/blog.json`.

```
RSS Feed ──► Extract Content ──► AI: Meta Title/Desc ──► AI: Keywords/Competitors ──► AI: Rewrite ──► blog.json
```

---

## Quick Start

### 1. Set your credentials

Open `rss-automation/config.php` and fill in:

```php
'rss_feed_url' => 'https://YOUR-RSS-FEED-URL-HERE',
'ai_provider'  => 'openai',           // or 'anthropic'
'ai_api_key'   => 'YOUR-API-KEY',
'ai_model'     => 'gpt-4o-mini',      // or 'claude-sonnet-4-6'
'serp_api_key' => 'YOUR-SERPAPI-KEY', // optional, for competitor research
```

Or use environment variables:
```bash
export RSS_FEED_URL="https://..."
export AI_API_KEY="sk-..."
export AI_PROVIDER="openai"
```

### 2. Run manually

```bash
php rss-automation/run.php
```

### 3. Automate with cron

Run every day at 8 AM:
```
0 8 * * * /usr/bin/php /path/to/rss-automation/run.php >> /var/log/rss-automation.log 2>&1
```

---

## Dashboard

Access the web dashboard at:
```
https://yoursite.com/rss-automation/dashboard.php
```

Features:
- View latest published articles
- Read real-time logs
- Trigger a manual run via browser

---

## File Structure

```
rss-automation/
├── config.php              ← Your settings (API keys, RSS URL)
├── run.php                 ← Main script (orchestrator)
├── dashboard.php           ← Web monitoring UI
├── logs/
│   └── automation.log      ← Runtime logs
└── modules/
    ├── Logger.php           ← Logging utility
    ├── RssFetcher.php       ← Stage 1: Fetch RSS items
    ├── ContentExtractor.php ← Stage 2: Get full article text
    ├── AiClient.php         ← OpenAI / Anthropic wrapper
    ├── MetaAnalyzer.php     ← Stage 3: SEO title + meta description
    ├── KeywordResearcher.php← Stage 4: Competitor + keyword analysis
    ├── ArticleRewriter.php  ← Stage 5: Full article rewrite
    └── DatabaseUpdater.php  ← Stage 6: Save to blog.json
```

---

## Supported AI Providers

| Provider  | Model examples                          |
|-----------|-----------------------------------------|
| OpenAI    | gpt-4o, gpt-4o-mini, gpt-3.5-turbo     |
| Anthropic | claude-sonnet-4-6, claude-haiku-4-5-... |

---

## Notes

- Articles already in the database are automatically skipped (duplicate detection).
- `max_articles_per_run` (default: 3) controls how many articles are processed per run.
- Set `target_language` to `ar` for Arabic output or `en` for English.
