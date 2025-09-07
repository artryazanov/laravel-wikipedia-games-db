<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCompanyPageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobFiltersFootnoteTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_ignores_bracket_tokens_in_company_links_and_names(): void
    {

        $title = 'Game With Footnotes';
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
                // Raw names contain bracket tokens that must be ignored
                'developers' => ['id Software', '[a]'],
                'publishers' => ['[1]', 'GT Interactive'],
                // Link titles also contain tokens to be filtered out
                'developers_link_titles' => ['Id Software', '[b]'],
                'publishers_link_titles' => ['[c]', 'GT Interactive'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        // Only real companies are dispatched
        Bus::assertDispatched(ProcessCompanyPageJob::class, fn ($job) => $job->pageTitle === 'Id Software');
        Bus::assertDispatched(ProcessCompanyPageJob::class, fn ($job) => $job->pageTitle === 'GT Interactive');
        Bus::assertDispatchedTimes(ProcessCompanyPageJob::class, 2);

        // Only real companies are persisted via taxonomy sync
        $this->assertSame(2, Company::count());
        $this->assertDatabaseMissing('wikipedia_game_companies', ['name' => '[a]']);
        $this->assertDatabaseMissing('wikipedia_game_companies', ['name' => '[1]']);
        $this->assertDatabaseHas('wikipedia_game_companies', ['name' => 'id Software']);
        $this->assertDatabaseHas('wikipedia_game_companies', ['name' => 'GT Interactive']);
    }
}
