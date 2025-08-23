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
This package ships a single consolidated migration that creates the following tables (with comments):

- `wikipedia_games` (core games)
- `wikipedia_game_genres`
- `wikipedia_game_platforms`
- `wikipedia_game_companies`
- `wikipedia_game_modes`
- `wikipedia_game_series`
- `wikipedia_game_engines`
- `wikipedia_game_game_genre` (pivot)
- `wikipedia_game_game_platform` (pivot)
- `wikipedia_game_game_mode` (pivot)
- `wikipedia_game_game_series` (pivot)
- `wikipedia_game_game_engine` (pivot)
- `wikipedia_game_game_company` (pivot, with `role` column: developer|publisher)

The migration checks for existence prior to creation, making it safer for incremental adoption.

## Usage
1) Start the scraping by dispatching the initial category job through the console command:

```bash
php artisan games:scrape-wikipedia --category="Category:Video games"
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

## License
MIT License. See the LICENSE file in your project or include your own licensing terms if you vendor this package.

## Credits
- Vendor: Artryazanov
- Built with Laravel Queue, HTTP client, and Symfony DomCrawler.
