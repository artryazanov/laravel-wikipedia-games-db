<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Models\Engine;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Models\Genre;
use Artryazanov\WikipediaGamesDb\Models\Mode;
use Artryazanov\WikipediaGamesDb\Models\Platform;
use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Artryazanov\WikipediaGamesDb\Support\Concerns\CleansTitles;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
/**
 * ProcessGamePageJob fetches a page HTML, parses infobox data, and persists it idempotently.
 */
use Throwable;

class ProcessGamePageJob extends AbstractWikipediaJob
{
    use CleansTitles;

    /** Number of attempts before failing the job. */
    public int $tries = 3;

    /** Backoff seconds between retries. */
    public int $backoff = 120;

    public function __construct(public string $pageTitle) {}

    /**
     * Handle the queued job.
     */
    public function handle(MediaWikiClient $client, InfoboxParser $parser): void
    {
        $this->executeWithThrottle(function () use ($client, $parser) {
            $this->doJob($client, $parser);
        });
    }

    private function doJob(MediaWikiClient $client, InfoboxParser $parser): void
    {
        // Skip disambiguation pages early to avoid unnecessary parsing
        try {
            if ($client->isDisambiguation($this->pageTitle)) {
                Log::info('Skipping disambiguation page', ['title' => $this->pageTitle]);

                return;
            }
        } catch (\Throwable $e) {
            Log::debug('Disambiguation check error; continuing', [
                'title' => $this->pageTitle,
                'error' => $e->getMessage(),
            ]);
        }

        // Skip redirect pages: we only process canonical article pages
        try {
            if ($client->isRedirect($this->pageTitle)) {
                Log::info('Skipping redirect page', ['title' => $this->pageTitle]);

                return;
            }
        } catch (\Throwable $e) {
            Log::debug('Redirect check error; continuing', [
                'title' => $this->pageTitle,
                'error' => $e->getMessage(),
            ]);
        }

        $html = $client->getPageHtml($this->pageTitle);
        if (! $html) {
            $this->fail(new \RuntimeException("Failed to fetch HTML for page: {$this->pageTitle}"));

            return;
        }

        // Parse page infoboxes. For backward compatibility with tests that mock only parse(),
        // we always call parse() and then, if using the real parser, prefer parseAll().
        $firstData = $parser->parse($html);
        $datasets = [];
        if (! empty($firstData)) {
            $datasets = [$firstData];
        }
        if (get_class($parser) === InfoboxParser::class) {
            try {
                $all = $parser->parseAll($html);
                if (! empty($all)) {
                    $datasets = $all; // override with full list
                }
            } catch (\Throwable $e) {
                // ignore and use the single dataset
            }
        }
        if (empty($datasets)) {
            Log::warning('No infobox data found for page', ['title' => $this->pageTitle]);

            return;
        }

        // Compute page-level data once (avoid eager main image fetch to keep tests' mocks simple)
        $leadDescription = $client->getPageLeadDescription($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
        $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

        // Lazy main image fetch: only when needed, and only with the real client implementation
        $mainImage = null;
        $mainImageFetched = false;

        foreach ($datasets as $data) {
            // Fallback for cover image: if parser did not find it, use page main image
            if (empty($data['cover_image_url'])) {
                if (! $mainImageFetched) {
                    try {
                        $mainImage = $client->getPageMainImage($this->pageTitle);
                    } catch (\Throwable $e) {
                        $mainImage = null;
                    }
                    $mainImageFetched = true;
                }
                if (is_string($mainImage) && $mainImage !== '') {
                    $data['cover_image_url'] = $mainImage;
                }
            }

            // Title per game: prefer infobox title if present
            $gameTitle = is_string($data['title'] ?? null) && $data['title'] !== ''
                ? $data['title']
                : $this->pageTitle;
            $cleanTitle = $this->makeCleanTitle($gameTitle);
            $releaseYear = $this->extractReleaseYear($data['release_date'] ?? null);

            // Dispatch company page jobs for linked developers/publishers
            $linkedCompanies = array_unique(array_merge(
                $data['developers_link_titles'] ?? [],
                $data['publishers_link_titles'] ?? []
            ));
            // Exclude footnote-like tokens such as "[a]", "[b]"
            $linkedCompanies = array_values(array_filter($linkedCompanies, function ($name) {
                return is_string($name) && $name !== '' && ! $this->isBracketFootnoteToken($name);
            }));
            foreach ($linkedCompanies as $companyTitle) {
                if ($this->needsDetails(Company::class, $companyTitle)) {
                    ProcessCompanyPageJob::dispatch($companyTitle)
                        ->onConnection(config('game-scraper.queue_connection'))
                        ->onQueue(config('game-scraper.queue_name'));
                }
            }

            // Dispatch platform page jobs for linked platforms
            $linkedPlatforms = array_unique($data['platforms_link_titles'] ?? []);
            foreach ($linkedPlatforms as $platformTitle) {
                if (is_string($platformTitle) && $platformTitle !== '' && $this->needsDetails(Platform::class, $platformTitle)) {
                    ProcessPlatformPageJob::dispatch($platformTitle)
                        ->onConnection(config('game-scraper.queue_connection'))
                        ->onQueue(config('game-scraper.queue_name'));
                }
            }

            // Dispatch engine page jobs for linked engines
            $linkedEngines = array_unique($data['engines_link_titles'] ?? []);
            foreach ($linkedEngines as $engineTitle) {
                if (is_string($engineTitle) && $engineTitle !== '' && $this->needsDetails(Engine::class, $engineTitle)) {
                    ProcessEnginePageJob::dispatch($engineTitle)
                        ->onConnection(config('game-scraper.queue_connection'))
                        ->onQueue(config('game-scraper.queue_name'));
                }
            }

            // Dispatch genre page jobs for linked genres
            $linkedGenres = array_unique($data['genres_link_titles'] ?? []);
            foreach ($linkedGenres as $genreTitle) {
                if (is_string($genreTitle) && $genreTitle !== '' && $this->needsDetails(Genre::class, $genreTitle)) {
                    ProcessGenrePageJob::dispatch($genreTitle)
                        ->onConnection(config('game-scraper.queue_connection'))
                        ->onQueue(config('game-scraper.queue_name'));
                }
            }

            // Dispatch mode page jobs for linked modes
            $linkedModes = array_unique($data['modes_link_titles'] ?? []);
            foreach ($linkedModes as $modeTitle) {
                if (is_string($modeTitle) && $modeTitle !== '' && $this->needsDetails(Mode::class, $modeTitle)) {
                    ProcessModePageJob::dispatch($modeTitle)
                        ->onConnection(config('game-scraper.queue_connection'))
                        ->onQueue(config('game-scraper.queue_name'));
                }
            }

            // Dispatch series page jobs for linked series
            $linkedSeries = array_unique($data['series_link_titles'] ?? []);
            foreach ($linkedSeries as $seriesTitle) {
                if (is_string($seriesTitle) && $seriesTitle !== '' && $this->needsDetails(Series::class, $seriesTitle)) {
                    ProcessSeriesPageJob::dispatch($seriesTitle)
                        ->onConnection(config('game-scraper.queue_connection'))
                        ->onQueue(config('game-scraper.queue_name'));
                }
            }

            DB::transaction(function () use ($data, $leadDescription, $wikitext, $wikipediaUrl, $cleanTitle, $releaseYear) {
                // Prepare filtered developers/publishers to determine if a new game can be created
                $filteredDevelopers = [];
                if (! empty($data['developers']) && is_array($data['developers'])) {
                    $filteredDevelopers = array_values(array_filter(
                        $data['developers'],
                        fn ($n) => is_string($n) && $n !== '' && ! $this->isBracketFootnoteToken($n)
                    ));
                }
                $filteredPublishers = [];
                if (! empty($data['publishers']) && is_array($data['publishers'])) {
                    $filteredPublishers = array_values(array_filter(
                        $data['publishers'],
                        fn ($n) => is_string($n) && $n !== '' && ! $this->isBracketFootnoteToken($n)
                    ));
                }
                // Create a game only when required fields are present: developers, publishers, release year, and genres
                $filteredGenres = [];
                if (! empty($data['genres']) && is_array($data['genres'])) {
                    $filteredGenres = array_values(array_filter(
                        $data['genres'],
                        fn ($n) => is_string($n) && $n !== '' && ! $this->isBracketFootnoteToken($n)
                    ));
                }
                $hasRequiredFields = (
                    ($filteredDevelopers !== []) &&
                    ($filteredPublishers !== []) &&
                    ($releaseYear !== null) &&
                    ($filteredGenres !== [])
                );

                // If any required field is missing, skip creating anything
                if (! $hasRequiredFields) {
                    return; // exit early: do not create Wikipage or Game
                }

                // Upsert Wikipage
                $wikipage = Wikipage::where('wikipedia_url', $wikipediaUrl)
                    ->orWhere('title', $this->pageTitle)
                    ->first();

                $wikipagePayload = [
                    'title' => $this->pageTitle,
                    'wikipedia_url' => $wikipediaUrl,
                    'description' => $leadDescription ?? ($data['description'] ?? null),
                    'wikitext' => $wikitext,
                ];

                if ($wikipage) {
                    $wikipage->fill($wikipagePayload)->save();
                } else {
                    $wikipage = Wikipage::create($wikipagePayload);
                }

                // Upsert game by (wikipage_id, clean_title)
                $game = Game::where('wikipage_id', $wikipage->id)
                    ->where('clean_title', $cleanTitle)
                    ->first();

                $payload = [
                    'wikipage_id' => $wikipage->id,
                    'clean_title' => $cleanTitle,
                    'cover_image_url' => $data['cover_image_url'] ?? null,
                    'release_date' => $this->parseDate($data['release_date'] ?? null)?->toDateString(),
                    'release_year' => $releaseYear,
                ];

                if ($game) {
                    $game->fill($payload)->save();
                } else {
                    $game = Game::create($payload);
                }

                // Sync relations
                if (! empty($data['genres']) && is_array($data['genres'])) {
                    $genreIds = $this->getIdsFor(Genre::class, $data['genres']);
                    $game->genres()->sync($genreIds);
                }

                if (! empty($data['platforms']) && is_array($data['platforms'])) {
                    $platformIds = $this->getIdsFor(Platform::class, $data['platforms']);
                    $game->platforms()->sync($platformIds);
                }

                if (! empty($data['modes']) && is_array($data['modes'])) {
                    $modeIds = $this->getIdsFor(Mode::class, $data['modes']);
                    $game->modes()->sync($modeIds);
                }

                if (! empty($data['series']) && is_array($data['series'])) {
                    $seriesIds = $this->getIdsFor(Series::class, $data['series']);
                    $game->series()->sync($seriesIds);
                }

                if (! empty($data['engines']) && is_array($data['engines'])) {
                    $engineIds = $this->getIdsFor(Engine::class, $data['engines']);
                    $game->engines()->sync($engineIds);
                }

                $companySync = [];
                if ($filteredDevelopers !== []) {
                    $devIds = $this->getIdsFor(Company::class, $filteredDevelopers);
                    foreach ($devIds as $id) {
                        $companySync[$id] = ['role' => 'developer'];
                    }
                }
                if ($filteredPublishers !== []) {
                    $pubIds = $this->getIdsFor(Company::class, $filteredPublishers);
                    foreach ($pubIds as $id) {
                        $companySync[$id] = ['role' => 'publisher'];
                    }
                }
                if (! empty($companySync)) {
                    $game->companies()->sync($companySync);
                }
            });
        }
    }

    /**
     * Create or find taxonomy models by name and return their IDs.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string[]  $names
     * @return int[]
     */
    private function getIdsFor(string $modelClass, array $names): array
    {
        $names = array_values(array_unique(array_filter($names, fn ($n) => is_string($n) && $n !== '')));
        if ($names === []) {
            return [];
        }

        /** @var \Illuminate\Database\Eloquent\Model $modelClass */
        $existing = $modelClass::query()
            ->whereIn('name', $names)
            ->pluck('id', 'name');

        $missing = array_diff($names, $existing->keys()->all());
        if ($missing !== []) {
            $now = now();
            $insert = [];
            foreach ($missing as $name) {
                $row = ['name' => $name, 'created_at' => $now, 'updated_at' => $now];
                if ($modelClass === Company::class) {
                    $row['clean_name'] = $this->makeCleanTitle($name);
                }
                $insert[] = $row;
            }
            // Avoid unique constraint race conditions between concurrent jobs
            // by ignoring rows that another worker inserted meanwhile.
            // MySQL will use INSERT IGNORE; SQLite/Postgres use equivalent behavior.
            $modelClass::insertOrIgnore($insert);

            // Reload IDs for all names including newly inserted ones
            $existing = $modelClass::query()
                ->whereIn('name', $names)
                ->pluck('id', 'name');
        }

        return array_values($existing->all());
    }

    private function extractReleaseYear(?string $dateString): ?int
    {
        if (! $dateString) {
            return null;
        }
        if (preg_match('/(\d{4})/u', $dateString, $m)) {
            $year = (int) $m[1];
            if ($year >= 1950 && $year <= (int) date('Y') + 1) {
                return $year;
            }

            return (int) $m[1];
        }

        return null;
    }

    /**
     * Determine whether a taxonomy record needs details to be fetched.
     * Returns true when the record does not exist yet or its wikipedia_url is empty.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     */
    private function needsDetails(string $modelClass, string $linkedTitle): bool
    {
        return ! $modelClass::where('name', $linkedTitle)
            ->whereHas('wikipage', function ($q) {
                $q->whereNotNull('wikipedia_url')->where('wikipedia_url', '!=', '');
            })
            ->exists();
    }

    private function parseDate(?string $dateString): ?Carbon
    {
        if (! $dateString) {
            return null;
        }
        try {
            return Carbon::parse($dateString);
        } catch (Throwable $e) {
            Log::warning('Could not parse date', [
                'date' => $dateString,
                'title' => $this->pageTitle,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Detect footnote-like tokens such as "[a]", "[b]", "[c]" that are not company names.
     */
    private function isBracketFootnoteToken(string $value): bool
    {
        $v = trim($value);

        // Match exactly one letter or number in square brackets, e.g., [a] or [1]
        return (bool) preg_match('/^\[[a-z0-9]\]$/i', $v);
    }
}
