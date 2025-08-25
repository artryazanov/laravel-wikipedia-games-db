<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Models\Engine;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Models\Genre;
use Artryazanov\WikipediaGamesDb\Models\Mode;
use Artryazanov\WikipediaGamesDb\Models\Platform;
use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * ProcessGamePageJob fetches a page HTML, parses infobox data, and persists it idempotently.
 */
class ProcessGamePageJob extends AbstractWikipediaJob
{
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

        DB::transaction(function () use ($data, $leadDescription, $cleanTitle, $wikitext, $releaseYear) {
            // Build wikipedia_url from title
            $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

            // Upsert game by title
            $game = Game::where('title', $this->pageTitle)->first();
            $payload = [
                'title' => $this->pageTitle,
                'clean_title' => $cleanTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? ($data['description'] ?? null),
                'wikitext' => $wikitext,
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'release_date' => $this->parseDate($data['release_date'] ?? null)?->toDateString(),
                'release_year' => $releaseYear,
            ];

            if ($game) {
                $game->fill($payload);
                $game->save();
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
                $devIds = $this->getIdsFor(Company::class, $data['developers']);
                foreach ($devIds as $id) {
                    $companySync[$id] = ['role' => 'developer'];
                }
            }
            if (! empty($data['publishers']) && is_array($data['publishers'])) {
                $pubIds = $this->getIdsFor(Company::class, $data['publishers']);
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
     * Create or find models by slug and return their IDs.
     *
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $modelClass
     * @param  string[]  $names
     * @return int[]
     */
    private function getIdsFor(string $modelClass, array $names): array
    {
        $ids = [];
        foreach ($names as $name) {
            $slug = $this->makeSlug($name, 255);
            /** @var \Illuminate\Database\Eloquent\Model $model */
            $model = $modelClass::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );
            $ids[] = $model->id;
        }

        return $ids;
    }

    private function makeSlug(string $name, int $maxLen = 255): string
    {
        $slug = Str::slug($name);
        if ($slug === '') {
            $hash = substr(sha1($name), 0, 12);
            $fallback = 'n-a-'.$hash;

            return substr($fallback, 0, $maxLen);
        }
        if (strlen($slug) <= $maxLen) {
            return $slug;
        }
        $hash = substr(sha1($name), 0, 8);
        $suffix = '-'.$hash;
        $baseLen = $maxLen - strlen($suffix);
        if ($baseLen < 1) {
            return substr($slug, 0, $maxLen);
        }
        $base = substr($slug, 0, $baseLen);
        $base = rtrim($base, '-');

        return $base.$suffix;
    }

    private function makeCleanTitle(string $title): string
    {
        // Remove trailing parenthetical disambiguators like "(1999 video game)", "(SNES)", etc.
        $clean = trim($title);
        while (preg_match('/\s*\([^()]*\)\s*$/u', $clean)) {
            $clean = preg_replace('/\s*\([^()]*\)\s*$/u', '', $clean) ?? $clean;
        }
        // Collapse multiple whitespace to a single space
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return trim($clean);
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
}
