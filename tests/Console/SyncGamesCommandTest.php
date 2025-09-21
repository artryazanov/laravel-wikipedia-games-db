<?php

namespace Tests\Console;

use Artryazanov\WikipediaGamesDb\Jobs\FetchGamesBatchJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncGamesCommandTest extends TestCase
{
    public function test_dispatches_first_batch_with_defaults_and_routing(): void
    {
        Http::fake();
        Bus::fake();

        // Configure specific connection and queue routing
        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');

        // No limit provided, command should use default 100
        $this->artisan('wikipedia:sync-games')
            ->assertSuccessful();

        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->limit === 100
                && $job->apcontinue === null
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }

    public function test_dispatches_with_provided_options(): void
    {
        Http::fake();
        Bus::fake();

        $this->artisan('wikipedia:sync-games', [
            '--limit' => 7,
            '--apcontinue' => 'foo|bar',
        ])->assertSuccessful();

        Bus::assertDispatched(FetchGamesBatchJob::class, function (FetchGamesBatchJob $job) {
            return $job->limit === 7 && $job->apcontinue === 'foo|bar';
        });
    }
}
