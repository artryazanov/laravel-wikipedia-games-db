<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\Facades\Log;

/**
 * FetchTemplateTransclusionsJob enumerates pages that embed a given template
 * (e.g., Template:Infobox video game) via list=embeddedin, and fans out
 * ProcessGamePageJob for each page found. Handles pagination via eicontinue.
 */
class FetchTemplateTransclusionsJob extends AbstractWikipediaJob
{
    /** Number of attempts before failing the job. */
    public int $tries = 3;

    /** Backoff seconds between retries. */
    public int $backoff = 60;

    public function __construct(
        public string $templateTitle,
        public ?string $continueToken = null
    ) {}

    public function handle(MediaWikiClient $client): void
    {
        $this->executeWithThrottle(function () use ($client) {
            $this->doJob($client);
        });
    }

    protected function doJob(MediaWikiClient $client): void
    {
        $data = $client->getEmbeddedIn($this->templateTitle, $this->continueToken);
        if (! $data) {
            $this->fail(new \RuntimeException("Failed to fetch embeddedin for template: {$this->templateTitle}"));

            return;
        }

        $count = 0;
        foreach ($data['members'] as $item) {
            $title = $item['title'] ?? '';
            if ($title === '') {
                continue;
            }

            // Skip obvious disambiguation pages early when possible
            try {
                if ($client->isDisambiguation($title)) {
                    continue;
                }
            } catch (\Throwable $e) {
                Log::debug('Disambiguation check failed, proceeding with page', [
                    'title' => $title,
                    'error' => $e->getMessage(),
                ]);
            }

            ProcessGamePageJob::dispatch($title)
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
            $count++;
        }

        if (! empty($data['continue'])) {
            self::dispatch($this->templateTitle, $data['continue'])
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
        }
    }
}

