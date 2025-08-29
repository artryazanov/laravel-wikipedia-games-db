<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessPlatformPageJob;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobDispatchPlatformsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_platform_jobs_for_linked_platforms(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'Game With Platform Links';
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
                'platforms' => ['Windows', 'PlayStation 5'],
                'platforms_link_titles' => ['Microsoft Windows', 'PlayStation 5'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        Bus::assertDispatched(ProcessPlatformPageJob::class, fn ($job) => $job->pageTitle === 'Microsoft Windows');
        Bus::assertDispatched(ProcessPlatformPageJob::class, fn ($job) => $job->pageTitle === 'PlayStation 5');
        Bus::assertDispatchedTimes(ProcessPlatformPageJob::class, 2);
    }
}
