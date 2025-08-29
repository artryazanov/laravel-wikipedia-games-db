<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Platform;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProcessPlatformPageJob fetches a Wikipedia platform page, parses data, and persists it.
 * It stores extended platform fields: title, wikipedia_url, description, wikitext,
 * cover_image_url, release_date, website_url.
 */
class ProcessPlatformPageJob extends AbstractWikipediaJob implements ShouldBeUnique
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
            $this->fail(new \RuntimeException("Failed to fetch HTML for platform page: {$this->pageTitle}"));

            return;
        }

        $data = $parser->parse($html);
        if (empty($data)) {
            Log::warning('No infobox data found for platform page', ['title' => $this->pageTitle]);

            return;
        }

        // Fallback for cover image
        if (empty($data['cover_image_url'])) {
            $mainImage = $client->getPageMainImage($this->pageTitle);
            if (is_string($mainImage) && $mainImage !== '') {
                $data['cover_image_url'] = $mainImage;
            }
        }

        $leadDescription = $client->getPageLeadDescription($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
        $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

        DB::transaction(function () use ($data, $leadDescription, $wikitext, $wikipediaUrl) {
            // Upsert Wikipage
            $wikipage = Wikipage::where('wikipedia_url', $wikipediaUrl)
                ->orWhere('title', $this->pageTitle)
                ->first();

            $wikipagePayload = [
                'title' => $this->pageTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? null,
                'wikitext' => $wikitext,
            ];
            if ($wikipage) {
                $wikipage->fill($wikipagePayload)->save();
            } else {
                $wikipage = Wikipage::create($wikipagePayload);
            }

            // Upsert Platform by name or attached wikipage
            $platform = Platform::where('name', $this->pageTitle)
                ->orWhere('wikipage_id', $wikipage->id)
                ->first();

            $payload = [
                'wikipage_id' => $wikipage->id,
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'release_date' => $this->parseDate($data['release_date'] ?? null)?->toDateString(),
                'website_url' => $data['website_url'] ?? null,
            ];

            if ($platform) {
                $platform->fill($payload)->save();
            } else {
                $platform = Platform::create(array_merge([
                    'name' => $this->pageTitle,
                ], $payload));
            }
        });
    }

    private function parseDate(?string $dateString): ?Carbon
    {
        if (! $dateString) {
            return null;
        }
        try {
            return Carbon::parse($dateString);
        } catch (Throwable $e) {
            Log::warning('Could not parse platform release date', [
                'date' => $dateString,
                'title' => $this->pageTitle,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
