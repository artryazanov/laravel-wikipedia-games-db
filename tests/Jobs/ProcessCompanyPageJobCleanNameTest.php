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
        // Minimal infobox HTML that InfoboxParser::parse will extract data from
        $html = <<<'HTML'
        <html>
            <body>
                <table class="infobox">
                    <tr>
                        <th>Founded</th>
                        <td>1996</td>
                    </tr>
                </table>
            </body>
        </html>
        HTML;

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->once()->with($title)->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wt');
        });

        // Use real parser so that provided HTML yields non-empty parsed data
        $parser = new InfoboxParser;

        (new ProcessCompanyPageJob($title))->handle($client, $parser);

        $company = Company::first();
        $this->assertNotNull($company);
        $this->assertSame('Valve', $company->clean_name);
        $this->assertSame('Valve (company)', $company->name);
    }
}
