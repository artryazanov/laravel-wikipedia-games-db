<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\FetchTemplateTransclusionsJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class FetchTemplateTransclusionsJobTest extends TestCase
{
    public function test_dispatches_game_pages_and_handles_continue_and_disambiguation(): void
    {
        Bus::fake();

        config()->set('game-scraper.queue_connection', 'sync');
        config()->set('game-scraper.queue_name', 'wikipedia');

        $tpl = 'Template:Infobox video game';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($tpl) {
            $mock->shouldReceive('getEmbeddedIn')
                ->once()
                ->with($tpl, null)
                ->andReturn([
                    'members' => [
                        ['title' => 'Game A', 'ns' => 0],
                        ['title' => 'Disambiguation Something', 'ns' => 0],
                    ],
                    'continue' => 'ei|123',
                ]);

            $mock->shouldReceive('isDisambiguation')
                ->once()
                ->with('Game A')
                ->andReturn(false);

            $mock->shouldReceive('isDisambiguation')
                ->once()
                ->with('Disambiguation Something')
                ->andReturn(true);
        });

        (new FetchTemplateTransclusionsJob($tpl))->handle($client);

        // Only non-disambiguation page should be dispatched
        Bus::assertDispatched(ProcessGamePageJob::class, function (ProcessGamePageJob $job) {
            return $job->pageTitle === 'Game A'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });

        // Continue re-dispatch for next page of results
        Bus::assertDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) use ($tpl) {
            return $job->templateTitle === $tpl
                && $job->continueToken === 'ei|123'
                && $job->connection === 'sync'
                && $job->queue === 'wikipedia';
        });
    }

    public function test_no_dispatch_when_client_returns_falsy(): void
    {
        Bus::fake();

        $tpl = 'Template:Infobox video game';
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($tpl) {
            $mock->shouldReceive('getEmbeddedIn')
                ->once()
                ->with($tpl, null)
                ->andReturn(null);
        });

        (new FetchTemplateTransclusionsJob($tpl))->handle($client);

        Bus::assertNotDispatched(ProcessGamePageJob::class);
        Bus::assertNotDispatched(FetchTemplateTransclusionsJob::class, function (FetchTemplateTransclusionsJob $job) {
            return $job->continueToken !== null;
        });
    }
}
