<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\Facades\Log;

/**
 * Fetches a batch of Wikipedia pages using list=allpages and enqueues processing
 * for each page via ProcessGamePageJob. Chains the next batch while the API
 * provides an apcontinue token.
 */
class FetchGamesBatchJob extends AbstractWikipediaJob
{
    public int $limit;

    public ?string $apcontinue;

    /** Mirror token name used by AbstractWikipediaJob::uniqueId() */
    public ?string $continueToken = null;

    public function __construct(int $limit, ?string $apcontinue = null)
    {
        $this->limit = max(1, $limit);
        $this->apcontinue = $apcontinue ?: null;
        $this->continueToken = $this->apcontinue;
    }

    public function handle(): void
    {
        $this->executeWithThrottle(function () {
            Log::info('Wikipedia FetchGamesBatchJob: fetching', [
                'apcontinue' => $this->apcontinue,
                'limit' => $this->limit,
            ]);

            $client = new MediaWikiClient(
                (string) config('game-scraper.api_endpoint'),
                (string) config('game-scraper.user_agent')
            );

            $result = $client->getAllPages($this->limit, $this->apcontinue);

            if ($result === null) {
                Log::error('Wikipedia API request failed', [
                    'apcontinue' => $this->apcontinue,
                    'limit' => $this->limit,
                ]);

                return; // Fail softly; the queue can retry if configured
            }

            $pages = $result['pages'] ?? [];
            $nextToken = $result['continue'] ?? null;

            if (empty($pages)) {
                Log::info('Wikipedia FetchGamesBatchJob: no more records to process', [
                    'apcontinue' => $this->apcontinue,
                ]);

                return;
            }

            $dispatched = 0;
            foreach ($pages as $p) {
                $title = $p['title'] ?? null;
                if (! $title) {
                    continue;
                }

                ProcessGamePageJob::dispatch($title)
                    ->onConnection(config('game-scraper.queue_connection'))
                    ->onQueue(config('game-scraper.queue_name'));
                $dispatched++;
            }

            Log::info('Wikipedia FetchGamesBatchJob: dispatched ProcessGamePageJob jobs', [
                'count' => $dispatched,
                'from_apcontinue' => $this->apcontinue,
            ]);

            // Chain next batch while API provides continuation token
            if ($nextToken) {
                self::dispatch($this->limit, $nextToken)
                    ->onConnection(config('game-scraper.queue_connection'))
                    ->onQueue(config('game-scraper.queue_name'));
            }
        });
    }
}
