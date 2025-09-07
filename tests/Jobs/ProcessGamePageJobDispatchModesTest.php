<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessModePageJob;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobDispatchModesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_mode_jobs_for_linked_modes(): void
    {

        $title = 'Game With Mode Links';
        $html = '<html></html>';

        Bus::fake();

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'modes' => ['Single-player', 'Multiplayer'],
                'modes_link_titles' => ['Single-player video game', 'Multiplayer video game'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        Bus::assertDispatched(ProcessModePageJob::class, fn ($job) => $job->pageTitle === 'Single-player video game');
        Bus::assertDispatched(ProcessModePageJob::class, fn ($job) => $job->pageTitle === 'Multiplayer video game');
        Bus::assertDispatchedTimes(ProcessModePageJob::class, 2);
    }
}
