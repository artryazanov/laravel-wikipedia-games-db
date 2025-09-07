<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCompanyPageJob;
use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessCompanyPageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extended_company_fields(): void
    {

        $title = 'Id Software';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('American video game developer');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('company wikitext');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'cover_image_url' => 'https://img/logo.jpg',
                'founded' => 1991,
                'website_url' => 'https://www.idsoftware.com',
            ]);
        });

        (new ProcessCompanyPageJob($title))->handle($client, $parser);

        $company = Company::with('wikipage')->first();
        $this->assertNotNull($company);
        $this->assertSame($title, $company->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/Id_Software', $company->wikipage->wikipedia_url);
        $this->assertSame('American video game developer', $company->wikipage->description);
        $this->assertSame('company wikitext', $company->wikipage->wikitext);
        $this->assertSame('https://img/logo.jpg', $company->cover_image_url);
        $this->assertSame(1991, (int) $company->founded);
        $this->assertSame('https://www.idsoftware.com', $company->website_url);
        $this->assertSame('Id Software', $company->name);
    }

    public function test_cover_image_fallback_used_when_missing(): void
    {

        $title = 'Valve';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->once()->with($title)->andReturn('https://img/fallback-logo.jpg');
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Company lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'founded' => 1996,
                'website_url' => 'https://www.valvesoftware.com',
                // no cover_image_url to trigger fallback
            ]);
        });

        (new ProcessCompanyPageJob($title))->handle($client, $parser);

        $company = Company::first();
        $this->assertSame('https://img/fallback-logo.jpg', $company->cover_image_url);
    }

    public function test_updates_existing_company_found_by_wikipedia_url(): void
    {
        $title = 'Id Software';
        $html = '<html></html>';

        // Pre-create company with different name but matching wikipedia_url via wikipage
        $wpId = \Artryazanov\WikipediaGamesDb\Models\Wikipage::create([
            'title' => 'Id Software',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Id_Software',
        ])->id;
        Company::create(['name' => 'Id Software, Inc.', 'wikipage_id' => $wpId]);

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Updated description');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('updated wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'cover_image_url' => 'https://img/logo2.jpg',
                'founded' => 1991,
                'website_url' => 'https://www.idsoftware.com',
            ]);
        });

        (new ProcessCompanyPageJob($title))->handle($client, $parser);

        $this->assertSame(1, Company::count());
        $company = Company::first();
        $this->assertSame('Updated description', $company->wikipage->description);
        $this->assertSame('Id Software', $company->wikipage->title);
    }
}
