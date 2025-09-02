# Laravel Wikipedia Games DB

A Laravel package to build a normalized database of video games by scraping Wikipedia. It uses a queue-driven architecture to traverse categories and parse game pages via the Wikipedia (MediaWiki) API and an HTML infobox parser.

By default, the package targets English Wikipedia and allows full configuration via environment variables.

## Features
- Queue-driven, resumable scraping workflow
- MediaWiki API client abstraction
- Robust infobox HTML parser (developers, publishers, genres, platforms, modes, series, engines, release date, cover image)
- Normalized schema with many-to-many relations
- All tables are prefixed (wikipedia_*) — core table is `wikipedia_games`
- Single consolidated migration for easier setup
- Configurable via `.env` (endpoint, user agent, throttling, queues)

## Requirements
- PHP >= 8.1
- Laravel 10.x, 11.x, or 12.x
- Extensions: curl, dom, mbstring, gd (typical for Laravel + DOM parsing)

## Installation
If this package is included as a path repository in your monorepo (as in this project), ensure your root composer.json has a repository entry pointing to `packages/artryazanov/laravel-wikipedia-games-db`, then require it:

```bash
composer require artryazanov/laravel-wikipedia-games-db:dev-main
```

If installing from a VCS/Packagist in another project, require it the same way and ensure Composer discovers the service provider (auto-discovery enabled). If needed, register the provider manually in `config/app.php`:

```php
'providers' => [
    // ...
    Artryazanov\WikipediaGamesDb\WikipediaGamesDbServiceProvider::class,
],
```

## Publish configuration and migrations
```bash
php artisan vendor:publish --provider="Artryazanov\\WikipediaGamesDb\\WikipediaGamesDbServiceProvider" --tag=config
php artisan vendor:publish --provider="Artryazanov\\WikipediaGamesDb\\WikipediaGamesDbServiceProvider" --tag=migrations
```

Then migrate:

```bash
php artisan migrate
```

## Configuration (.env)
All settings can be overridden via environment variables.

- `WIKIPEDIA_GAMES_DB_API_ENDPOINT` (default: `https://en.wikipedia.org/w/api.php`)
- `WIKIPEDIA_GAMES_DB_USER_AGENT` (default example: `LaravelWikipediaGamesDb/1.0 (+https://example.com; contact@example.com)`)
- `WIKIPEDIA_GAMES_DB_ROOT_CATEGORY` (default: `Category:Video games`)
- `WIKIPEDIA_GAMES_DB_THROTTLE_MS` (default: `1000`)
- `WIKIPEDIA_GAMES_DB_QUEUE_CONNECTION` (default: `null` — uses Laravel default)
- `WIKIPEDIA_GAMES_DB_QUEUE_NAME` (default: `default`)

Example snippet for your `.env`:

```dotenv
WIKIPEDIA_GAMES_DB_API_ENDPOINT=https://en.wikipedia.org/w/api.php
WIKIPEDIA_GAMES_DB_USER_AGENT="YourApp/1.0 (+https://your-site; you@example.com)"
WIKIPEDIA_GAMES_DB_ROOT_CATEGORY="Category:Video games"
WIKIPEDIA_GAMES_DB_THROTTLE_MS=1000
WIKIPEDIA_GAMES_DB_QUEUE_CONNECTION=
WIKIPEDIA_GAMES_DB_QUEUE_NAME=default
```

Please set a meaningful User-Agent per MediaWiki API etiquette.

## Database schema
This package ships migrations that create the following tables (with comments):

- `wikipedia_game_wikipages`: central storage for Wikipedia page meta reused by multiple entities. Columns: `title`, `wikipedia_url`, `description`, `wikitext`, timestamps.
- `wikipedia_games` (core games) — now has `wikipage_id` pointing to `wikipedia_game_wikipages`; still stores `clean_title`, `cover_image_url`, `release_date`, `release_year`.
- `wikipedia_game_genres` — has `wikipage_id`.
- `wikipedia_game_platforms` — has `wikipage_id` and keeps platform-specific fields like `cover_image_url`, `release_date`, `website_url`.
- `wikipedia_game_companies` — has `wikipage_id` and keeps `cover_image_url`, `founded`, `website_url`.
- `wikipedia_game_modes` — has `wikipage_id`.
- `wikipedia_game_series` — has `wikipage_id`.
- `wikipedia_game_engines` — has `wikipage_id` and keeps `cover_image_url`, `release_date`, `website_url`.
- `wikipedia_game_game_genre` (pivot)
- `wikipedia_game_game_platform` (pivot)
- `wikipedia_game_game_mode` (pivot)
- `wikipedia_game_game_series` (pivot)
- `wikipedia_game_game_engine` (pivot)
- `wikipedia_game_game_company` (pivot, with `role` column: developer|publisher)

The migrations check for existence prior to creation, making it safer for incremental adoption. A data migration backfills `wikipage_id` and moves `title`, `wikipedia_url`, `description`, `wikitext` into `wikipedia_game_wikipages`.

## Usage
You can kick off discovery in multiple ways. The fastest, high-precision path is via template transclusions.

1) Run all discovery strategies in one go (templates + categories):

```bash
php artisan games:scan-all
```

2) Discover via Infobox template (recommended for precise bootstrap):

```bash
php artisan games:discover-by-template
```

This enumerates all pages that include `Template:Infobox video game` (main namespace) and enqueues parsing jobs. To also include series/franchises:

```bash
php artisan games:discover-by-template --series
```

3) Traverse categories (broad coverage; longer):

```bash
php artisan games:scrape-wikipedia --category="Category:Video games"
```

Or seed multiple high-value roots (platforms and genres):

```bash
php artisan games:scrape-wikipedia --seed-high-value
```

If `--category` is omitted, the command uses `game-scraper.root_category` from config (by default, English `Category:Video games`).

2) Run your queue worker so jobs are processed:

```bash
php artisan queue:work --queue="${WIKIPEDIA_GAMES_DB_QUEUE_NAME:-default}"
```

Tips:
- Adjust `WIKIPEDIA_GAMES_DB_THROTTLE_MS` to respect API limits (start with 1000 ms).
- Set a meaningful `WIKIPEDIA_GAMES_DB_USER_AGENT`.
- Ensure your queue driver is configured (`QUEUE_CONNECTION` and, optionally, `WIKIPEDIA_GAMES_DB_QUEUE_CONNECTION`).
- Prefer running the template-based discovery first to quickly build a large, accurate dataset; use category traversal to expand coverage over time.

## Scheduling (optional)
You can schedule periodic updates (e.g., weekly) in `app/Console/Kernel.php`:

```php
protected function schedule(\Illuminate\Console\Scheduling\Schedule $schedule): void
{
    $schedule->command('games:scrape-wikipedia')->weekly()->sundays()->at('03:00');
}
```

## Troubleshooting
- "No jobs processed" — ensure a queue worker is running and the queue name/connection align with your config and env.
- 429 / throttling from API — increase `WIKIPEDIA_GAMES_DB_THROTTLE_MS` and verify your User-Agent.
- Migrations not found — run the vendor:publish step for migrations or rely on the package-loaded migrations.

## Background jobs & conditional dispatch
This package processes pages via queued jobs. The main entry point parses a game page and conditionally enqueues per-taxonomy jobs for additional details.

- ProcessGamePageJob: Parses a game page, upserts a central `Wikipage`, persists game-specific fields, and dispatches taxonomy jobs for linked items found in the infobox (developers, publishers, platforms, engines, genres, modes, series).
- ProcessCompanyPageJob: Upserts `Wikipage` and persists company-specific fields (`cover_image_url`, `founded`, `website_url`).
- ProcessPlatformPageJob: Upserts `Wikipage` and persists platform-specific fields (`cover_image_url`, `release_date`, `website_url`).
- ProcessEnginePageJob: Upserts `Wikipage` and persists engine-specific fields (`cover_image_url`, `release_date`, `website_url`).
- ProcessGenrePageJob: Upserts `Wikipage` and links the genre.
- ProcessModePageJob: Upserts `Wikipage` and links the mode.
- ProcessSeriesPageJob: Upserts `Wikipage` and links the series.

Conditional dispatch
- ProcessGamePageJob will only enqueue a Process*PageJob when the corresponding record is missing or when its linked `wikipage.wikipedia_url` is empty.
- This minimizes redundant requests and focuses fetching on missing details.

Throttling and deduping
- All jobs inherit a throttle helper that respects `game-scraper.throttle_milliseconds` to avoid exceeding API limits.
- Jobs are idempotent: models are upserted and relations synced, so reprocessing the same page is safe. Queue-level uniqueness is not enabled by default; if you need strict uniqueness, you can implement `ShouldBeUnique` on specific jobs in your app fork.

## Testing
This repository includes a full test suite based on Orchestra Testbench with an in-memory SQLite database.

- Install dependencies:
  - composer install
- Run tests (Windows):
  - .\vendor\bin\phpunit --configuration phpunit.xml
- Run tests (Unix/macOS):
  - vendor/bin/phpunit --configuration phpunit.xml

You can also run via Composer script: `composer test`.

If phpunit cannot be found, ensure Composer finished installing dependencies successfully.

## License
The Unlicense. This is free and unencumbered software released into the public domain. See the LICENSE file or https://unlicense.org for details.

## Credits
- Vendor: Artryazanov
- Built with Laravel Queue, HTTP client, and Symfony DomCrawler.
