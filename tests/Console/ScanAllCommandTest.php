<?php

namespace Tests\Console;

use Artryazanov\WikipediaGamesDb\Jobs\FetchTemplateTransclusionsJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessCategoryJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ScanAllCommandTest extends TestCase
{
    public function test_dispatches_templates_and_categories_with_configured_routing(): void
    {
        Http::fake();
        Bus::fake();

        // Configure specific connection and queue and root category
        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');
        config()->set('game-scraper.root_category', 'Category:Video games');

        $this->artisan('games:scan-all')->assertSuccessful();

        // Templates
        Bus::assertDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) {
            return $job->templateTitle === 'Template:Infobox video game'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
        Bus::assertDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) {
            return $job->templateTitle === 'Template:Infobox video game series'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });

        // Categories
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
        Bus::assertDispatched(ProcessCategoryJob::class, function (ProcessCategoryJob $job) {
            return $job->categoryTitle === 'Category:Video games'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }
}

