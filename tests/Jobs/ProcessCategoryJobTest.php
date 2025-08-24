<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessCategoryJobTest extends TestCase
{
    public function test_dispatches_subcategory_and_game_jobs_and_continue(): void
    {
        Bus::fake();

        // Ensure specific routing configuration is respected
        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');

        $category = 'Category:Video games by year';

        // Stub client
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($category) {
            $mock->shouldReceive('getCategoryMembers')
                ->once()
                ->with($category, null)
                ->andReturn([
                    'members' => [
                        ['title' => 'Category:1993 video games', 'type' => 'subcat'],
                        ['title' => 'Doom (1993 video game)', 'type' => 'page'],
                    ],
                    'continue' => 'abc123',
                ]);
        });

        // Execute job handle directly
        (new ProcessCategoryJob($category))->handle($client);

        // Subcategory dispatched
        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) {
            return $job->categoryTitle === 'Category:1993 video games'
                && $job->continueToken === null
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });

        // Game page dispatched
        Bus::assertDispatched(ProcessGamePageJob::class, function (ProcessGamePageJob $job) {
            return $job->pageTitle === 'Doom (1993 video game)'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });

        // Continue token re-dispatch for same category
        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) use ($category) {
            return $job->categoryTitle === $category
                && $job->continueToken === 'abc123'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }

    public function test_no_dispatch_when_client_returns_falsy(): void
    {
        Bus::fake();

        $category = 'Category:Empty';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($category) {
            $mock->shouldReceive('getCategoryMembers')
                ->once()
                ->with($category, null)
                ->andReturn(null);
        });

        (new ProcessCategoryJob($category))->handle($client);

        Bus::assertNotDispatched(ProcessCategoryJob::class);
        Bus::assertNotDispatched(ProcessGamePageJob::class);
    }
}
