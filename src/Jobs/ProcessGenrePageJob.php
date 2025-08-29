<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Models\Genre;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ProcessGenrePageJob fetches a Wikipedia genre page, parses data, and persists it.
 * It stores extended genre fields: title, wikipedia_url, description, wikitext.
 */
class ProcessGenrePageJob extends AbstractWikipediaJob implements ShouldBeUnique
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
            $this->fail(new \RuntimeException("Failed to fetch HTML for genre page: {$this->pageTitle}"));

            return;
        }

        $data = $parser->parse($html);
        if (empty($data)) {
            Log::warning('No infobox data found for genre page', ['title' => $this->pageTitle]);
            // Even if no infobox, we still can save description and wikitext
        }

        $leadDescription = $client->getPageLeadDescription($this->pageTitle);
        $wikitext = $client->getPageWikitext($this->pageTitle);
        $wikipediaUrl = 'https://en.wikipedia.org/wiki/'.str_replace(' ', '_', $this->pageTitle);

        DB::transaction(function () use ($leadDescription, $wikitext, $wikipediaUrl) {
            // Try to find an existing genre by name or wikipedia_url
            $genre = Genre::where('name', $this->pageTitle)
                ->orWhere('wikipedia_url', $wikipediaUrl)
                ->first();

            $payload = [
                'title' => $this->pageTitle,
                'wikipedia_url' => $wikipediaUrl,
                'description' => $leadDescription ?? null,
                'wikitext' => $wikitext,
            ];

            if ($genre) {
                $genre->fill($payload);
                $genre->save();
            } else {
                $genre = Genre::create(array_merge([
                    'name' => $this->pageTitle,
                ], $payload));
            }
        });
    }
}
