<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Mode;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessModePageJob fetches a Wikipedia mode page, parses data, and persists it.
 * It stores extended mode fields: title, wikipedia_url, description, wikitext.
 */
class ProcessModePageJob extends AbstractWikipediaJob implements ShouldBeUnique
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
            // Find an existing mode by name or wikipedia_url
            $mode = Mode::where('name', $this->pageTitle)
                ->orWhere('wikipedia_url', $wikipediaUrl)
                ->first();

            $payload = [
                'title' => $this->pageTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? null,
                'wikitext' => $wikitext,
            ];

            if ($mode) {
                $mode->fill($payload);
                $mode->save();
            } else {
                $mode = Mode::create(array_merge([
                    'name' => $this->pageTitle,
                ], $payload));
            }
        });
    }
}
