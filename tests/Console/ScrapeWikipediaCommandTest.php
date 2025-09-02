<?php

namespace Tests\Console;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScrapeWikipediaCommandTest extends TestCase
{
    public function test_dispatches_with_provided_category_and_configured_connection_and_queue(): void
    {
        Http::fake();
        Bus::fake();

        // Configure specific connection and queue
        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');

        $category = 'Category:My Custom Games';

        $this->artisan('games:scrape-wikipedia', [
            '--category' => $category,
        ])
            ->assertSuccessful();

        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) use ($category) {
            // Assert the payload and routing
            return $job->categoryTitle === $category
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }

    public function test_dispatches_with_root_category_when_no_option(): void
    {
        Http::fake();
        Bus::fake();

        // Use default root category from TestCase
        $root = config('game-scraper.root_category');

        $this->artisan('games:scrape-wikipedia')
            ->assertSuccessful();

        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) use ($root) {
            return $job->categoryTitle === $root;
        });
    }

    public function test_errors_when_no_category_configured(): void
    {
        Http::fake();
        Bus::fake();

        config()->set('game-scraper.root_category', null);

        $this->artisan('games:scrape-wikipedia')
            ->expectsOutput('No start category provided and no root_category configured.')
            ->assertExitCode(1);

        Bus::assertNotDispatched(ProcessCategoryJob::class);
    }

    public function test_seed_high_value_dispatches_platform_and_genre_roots(): void
    {
        Http::fake();
        Bus::fake();

        // Configure routing
        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');

        $this->artisan('games:scrape-wikipedia', ['--seed-high-value' => true])
            ->assertSuccessful();

        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) {
            return $job->categoryTitle === 'Category:Video games by platform'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });

        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) {
            return $job->categoryTitle === 'Category:Video games by genre'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }
}
