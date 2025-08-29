<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * ProcessSeriesPageJob fetches a Wikipedia series page, parses data, and persists it.
 * It stores extended series fields: title, wikipedia_url, description, wikitext.
 */
class ProcessSeriesPageJob extends AbstractWikipediaJob implements ShouldBeUnique
{
    /** Number of attempts before failing the job. */
    public int $tries = 3;

    /** Backoff seconds between retries. */
    public int $backoff = 120;

    public function __construct(public string $pageTitle) {}

    /**
     * Unique identifier for this job to prevent duplicate enqueues for the same page.
     */
    public function uniqueId(): string
    {
        return static::class.':'.$this->pageTitle;
    }

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
            $this->fail(new \RuntimeException("Failed to fetch HTML for series page: {$this->pageTitle}"));

            return;
        }

        $data = $parser->parse($html);
        if (empty($data)) {
            Log::warning('No infobox data found for series page', ['title' => $this->pageTitle]);
            // Even if no infobox, we still can save description and wikitext
        }

        $leadDescription = $client->getPageLeadDescription($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
        $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

        DB::transaction(function () use ($leadDescription, $wikitext, $wikipediaUrl) {
            // Try to find an existing series by name or slug derived from the page title
            $slugFromTitle = $this->makeSlug($this->pageTitle, 255);
            $series = Series::where('name', $this->pageTitle)
                ->orWhere('slug', $slugFromTitle)
                ->first();

            $payload = [
                'title' => $this->pageTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? null,
                'wikitext' => $wikitext,
            ];

            if ($series) {
                $series->fill($payload);
                $series->save();
            } else {
                $series = Series::create(array_merge([
                    'name' => $this->pageTitle,
                    'slug' => $slugFromTitle,
                ], $payload));
            }
        });
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
}
