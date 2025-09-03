<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCompanyPageJob;
use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessCompanyPageJobCleanNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_sets_clean_name_from_title(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'Valve (company)';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                // No extra fields needed for this assertion
            ]);
        });

        (new ProcessCompanyPageJob($title))->handle($client, $parser);

        $company = Company::first();
        $this->assertNotNull($company);
        $this->assertSame('Valve', $company->clean_name);
        $this->assertSame('Valve (company)', $company->name);
    }
}
