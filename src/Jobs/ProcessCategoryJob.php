<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;

/**
 * ProcessCategoryJob processes one page of MediaWiki category members and fans out jobs.
 */
class ProcessCategoryJob extends AbstractWikipediaJob
{
    /** Number of attempts before failing the job. */
    public int $tries = 3;

    /** Backoff seconds between retries. */
    public int $backoff = 60;

    public function __construct(
        public string $categoryTitle,
        public ?string $continueToken = null
    ) {}

    /**
     * Handle the queued job.
     */
    public function handle(MediaWikiClient $client): void
    {
        $this->executeWithThrottle(function () use ($client) {
            $this->doJob($client);
        });
    }

    protected function doJob(MediaWikiClient $client): void
    {
        $data = $client->getCategoryMembers($this->categoryTitle, $this->continueToken);
        if (! $data) {
            // Fail gracefully so it can be retried
            $this->fail(new \RuntimeException("Failed to fetch members for category: {$this->categoryTitle}"));
            return;
        }

        foreach ($data['members'] as $member) {
            $title = $member['title'] ?? '';
            if ($title === '') {
                continue;
            }
            if (($member['type'] ?? 'page') === 'subcat') {
                self::dispatch($title)
                    ->onConnection(config('game-scraper.queue_connection'))
                    ->onQueue(config('game-scraper.queue_name'));
            } else {
                ProcessGamePageJob::dispatch($title)
                    ->onConnection(config('game-scraper.queue_connection'))
                    ->onQueue(config('game-scraper.queue_name'));
            }
        }

        if (! empty($data['continue'])) {
            self::dispatch($this->categoryTitle, $data['continue'])
                ->onConnection(config('game-scraper.queue_connection'))
                ->onQueue(config('game-scraper.queue_name'));
        }
    }
}
