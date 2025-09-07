<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCompanyPageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobDispatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_dispatches_company_jobs_for_linked_companies(): void
    {

        $title = 'Game With Links';
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

        // Mock InfoboxParser to include *_link_titles
        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'developers' => ['id Software'],
                'publishers' => ['GT Interactive'],
                'developers_link_titles' => ['Id Software'],
                'publishers_link_titles' => ['GT Interactive'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        Bus::assertDispatched(ProcessCompanyPageJob::class, function ($job) {
            return $job->pageTitle === 'Id Software';
        });
        Bus::assertDispatched(ProcessCompanyPageJob::class, function ($job) {
            return $job->pageTitle === 'GT Interactive';
        });
        Bus::assertDispatchedTimes(ProcessCompanyPageJob::class, 2);
    }
}
