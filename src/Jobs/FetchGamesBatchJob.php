<?php

namespace Artryazanov\WikipediaGamesDb\Jobs;

use Illuminate\Support\Facades\Http;
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
            $apiUrl = (string) config('game-scraper.api_endpoint'); // e.g. https://en.wikipedia.org/w/api.php

            Log::info('Wikipedia FetchGamesBatchJob: fetching', [
                'apcontinue' => $this->apcontinue,
                'limit' => $this->limit,
            ]);

            $params = [
                'action' => 'query',
                'list' => 'allpages',
                'aplimit' => $this->limit,
                'apnamespace' => '0', // main/article namespace only
                'format' => 'json',
            ];
            if ($this->apcontinue) {
                $params['apcontinue'] = $this->apcontinue;
            }

            $response = Http::timeout(30)->get($apiUrl, $params);

            if (! $response->ok()) {
                Log::error('Wikipedia API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'apcontinue' => $this->apcontinue,
                    'limit' => $this->limit,
                ]);

                return; // Fail softly; the queue can retry if configured
            }

            $data = $response->json();

            if (isset($data['error'])) {
                Log::error('Wikipedia API error', $data['error']);

                return;
            }

            $pages = $data['query']['allpages'] ?? [];

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
            $nextToken = $data['continue']['apcontinue'] ?? null;
            if ($nextToken) {
                self::dispatch($this->limit, $nextToken)
                    ->onConnection(config('game-scraper.queue_connection'))
                    ->onQueue(config('game-scraper.queue_name'));
            }
        });
    }
}
