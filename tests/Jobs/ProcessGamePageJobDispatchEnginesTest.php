<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessEnginePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobDispatchEnginesTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_engine_jobs_for_linked_engines(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'Game With Engine Links';
        $html = '<html></html>';

        Bus::fake();

        // Mock MediaWikiClient
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            // Allow optional image fallback call
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        // Mock InfoboxParser to include engines_link_titles
        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'engines' => ['Unreal Engine', 'Unity'],
                'engines_link_titles' => ['Unreal Engine', 'Unity (game engine)'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        Bus::assertDispatched(ProcessEnginePageJob::class, fn ($job) => $job->pageTitle === 'Unreal Engine');
        Bus::assertDispatched(ProcessEnginePageJob::class, fn ($job) => $job->pageTitle === 'Unity (game engine)');
        Bus::assertDispatchedTimes(ProcessEnginePageJob::class, 2);
    }
}
