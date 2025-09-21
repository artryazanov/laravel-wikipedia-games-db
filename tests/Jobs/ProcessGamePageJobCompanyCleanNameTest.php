<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessGamePageJobCompanyCleanNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_sets_clean_name_when_creating_companies_via_game_job(): void
    {

        $title = 'Some Game Title';
        $html = '<html></html>';

        // Mock MediaWikiClient
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
            $mock->shouldReceive('isDisambiguation')->andReturnFalse();
        });

        // Mock InfoboxParser to return company names that require cleaning
        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'developers' => ['Valve (company)'],
                'publishers' => ['Nintendo   '],
                'release_date' => '2001-02-03',
                'genres' => ['Action-Adventure'],
            ]);
        });

        // Execute job
        (new ProcessGamePageJob($title))->handle($client, $parser);

        // Ensure companies were created with cleaned names
        $this->assertSame(2, Company::count());

        $valve = Company::where('name', 'Valve (company)')->first();
        $this->assertNotNull($valve);
        $this->assertSame('Valve', $valve->clean_name);

        $nintendo = Company::where('name', 'Nintendo   ')->first();
        $this->assertNotNull($nintendo);
        $this->assertSame('Nintendo', $nintendo->clean_name);
    }
}
