<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

            // Upsert Series by name or attached wikipage
            $series = Series::where('name', $this->pageTitle)
                ->orWhere('wikipage_id', $wikipage->id)
                ->first();

            $payload = [
                'wikipage_id' => $wikipage->id,
            ];

            if ($series) {
                $series->fill($payload)->save();
            } else {
                $series = Series::create(array_merge([
                    'name' => $this->pageTitle,
                ], $payload));
            }
        });
    }
}
