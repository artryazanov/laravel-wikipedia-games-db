<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * ProcessCategoryJob processes one page of MediaWiki category members and fans out jobs.
 */
class ProcessCategoryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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
        Log::info('Processing category', [
            'category' => $this->categoryTitle,
            'continue' => $this->continueToken,
        ]);

        // Throttle to respect API etiquette
        $delayMs = (int) config('game-scraper.throttle_milliseconds', 1000);
        if ($delayMs > 0) {
            usleep($delayMs * 1000);
        }

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
