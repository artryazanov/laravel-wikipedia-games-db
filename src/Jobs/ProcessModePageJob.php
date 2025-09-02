<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Mode;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessModePageJob fetches a Wikipedia mode page, parses data, and persists it.
 * It stores extended mode fields: title, wikipedia_url, description, wikitext.
 */
class ProcessModePageJob extends AbstractWikipediaJob
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
            $this->fail(new \RuntimeException("Failed to fetch HTML for mode page: {$this->pageTitle}"));

            return;
        }

        $data = $parser->parse($html);
        if (empty($data)) {
            Log::warning('No infobox data found for mode page', ['title' => $this->pageTitle]);
            // Even without infobox, lead description and wikitext are useful
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

            // Upsert Mode by name or attached wikipage
            $mode = Mode::where('name', $this->pageTitle)
                ->orWhere('wikipage_id', $wikipage->id)
                ->first();

            $payload = [
                'wikipage_id' => $wikipage->id,
            ];

            if ($mode) {
                $mode->fill($payload)->save();
            } else {
                $mode = Mode::create(array_merge([
                    'name' => $this->pageTitle,
                ], $payload));
            }
        });
    }
}
