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

        $html = $client->getPageHtml($this->pageTitle);
        if (! $html) {
            $this->fail(new \RuntimeException("Failed to fetch HTML for page: {$this->pageTitle}"));

            return;
        }

        $data = $parser->parse($html);
        if (empty($data)) {
            Log::warning('No infobox data found for page', ['title' => $this->pageTitle]);

            return;
        }

        // Fallback for cover image: if parser did not find it, fetch via Wikimedia APIs
        if (empty($data['cover_image_url'])) {
            $mainImage = $client->getPageMainImage($this->pageTitle);
            if (is_string($mainImage) && $mainImage !== '') {
                $data['cover_image_url'] = $mainImage;
            }
        }

        $leadDescription = $client->getPageLeadDescription($this->pageTitle);
        $cleanTitle = $this->makeCleanTitle($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
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
                ProcessCompanyPageJob::dispatch($companyTitle);
            }
        }

        // Dispatch platform page jobs for linked platforms
        $linkedPlatforms = array_unique($data['platforms_link_titles'] ?? []);
        foreach ($linkedPlatforms as $platformTitle) {
            if (is_string($platformTitle) && $platformTitle !== '' && $this->needsDetails(Platform::class, $platformTitle)) {
                ProcessPlatformPageJob::dispatch($platformTitle);
            }
        }

        // Dispatch engine page jobs for linked engines
        $linkedEngines = array_unique($data['engines_link_titles'] ?? []);
        foreach ($linkedEngines as $engineTitle) {
            if (is_string($engineTitle) && $engineTitle !== '' && $this->needsDetails(Engine::class, $engineTitle)) {
                ProcessEnginePageJob::dispatch($engineTitle);
            }
        }

        // Dispatch genre page jobs for linked genres
        $linkedGenres = array_unique($data['genres_link_titles'] ?? []);
        foreach ($linkedGenres as $genreTitle) {
            if (is_string($genreTitle) && $genreTitle !== '' && $this->needsDetails(Genre::class, $genreTitle)) {
                ProcessGenrePageJob::dispatch($genreTitle);
            }
        }

        // Dispatch mode page jobs for linked modes
        $linkedModes = array_unique($data['modes_link_titles'] ?? []);
        foreach ($linkedModes as $modeTitle) {
            if (is_string($modeTitle) && $modeTitle !== '' && $this->needsDetails(Mode::class, $modeTitle)) {
                ProcessModePageJob::dispatch($modeTitle);
            }
        }

        // Dispatch series page jobs for linked series
        $linkedSeries = array_unique($data['series_link_titles'] ?? []);
        foreach ($linkedSeries as $seriesTitle) {
            if (is_string($seriesTitle) && $seriesTitle !== '' && $this->needsDetails(Series::class, $seriesTitle)) {
                ProcessSeriesPageJob::dispatch($seriesTitle);
            }
        }

        DB::transaction(function () use ($data, $leadDescription, $cleanTitle, $wikitext, $releaseYear) {
            // Build wikipedia_url from title
            $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

            // Upsert Wikipage by URL (preferred) or title
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

            // Upsert game by attached wikipage
            $game = Game::where('wikipage_id', $wikipage->id)->first();
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
            if (! empty($data['developers']) && is_array($data['developers'])) {
                // Filter out tokens like "[a]", "[b]"
                $developers = array_values(array_filter($data['developers'], fn ($n) => is_string($n) && $n !== '' && ! $this->isBracketFootnoteToken($n)));
                $devIds = $this->getIdsFor(Company::class, $developers);
                foreach ($devIds as $id) {
                    $companySync[$id] = ['role' => 'developer'];
                }
            }
            if (! empty($data['publishers']) && is_array($data['publishers'])) {
                // Filter out tokens like "[a]", "[b]"
                $publishers = array_values(array_filter($data['publishers'], fn ($n) => is_string($n) && $n !== '' && ! $this->isBracketFootnoteToken($n)));
                $pubIds = $this->getIdsFor(Company::class, $publishers);
                foreach ($pubIds as $id) {
                    $companySync[$id] = ['role' => 'publisher'];
                }
            }
            if (! empty($companySync)) {
                $game->companies()->sync($companySync);
            }
        });
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
        $ids = [];
        foreach ($names as $name) {
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $modelClass::firstOrCreate(
                ['name' => $name]
            );
            $ids[] = $model->id;
        }

        return $ids;
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
        /** @var \Illuminate\Database\Eloquent\Model|null $record */
        $record = $modelClass::where('name', $linkedTitle)->first();

        if (! $record) {
            return true;
        }

        $url = optional($record->wikipage)->wikipedia_url ?? null;

        return ! is_string($url) || trim((string) $url) === '';
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
