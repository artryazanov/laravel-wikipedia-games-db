<?php

namespace Tests\Console;

use Artryazanov\WikipediaGamesDb\Jobs\FetchTemplateTransclusionsJob;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscoverByTemplateCommandTest extends TestCase
{
    public function test_dispatches_default_template_job_with_configured_routing(): void
    {
        Http::fake();
        Bus::fake();

        // Configure specific connection and queue
        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');

        $this->artisan('games:discover-by-template')
            ->assertSuccessful();

        Bus::assertDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) {
            return $job->templateTitle === 'Template:Infobox video game'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }

    public function test_dispatches_series_template_when_option_passed(): void
    {
        Http::fake();
        Bus::fake();

        $this->artisan('games:discover-by-template', ['--series' => true])
            ->assertSuccessful();

        Bus::assertDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) {
            return $job->templateTitle === 'Template:Infobox video game';
        });

        Bus::assertDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) {
            return $job->templateTitle === 'Template:Infobox video game series';
        });
    }
}

