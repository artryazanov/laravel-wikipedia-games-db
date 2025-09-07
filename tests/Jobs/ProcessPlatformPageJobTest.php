<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessPlatformPageJob;
use Artryazanov\WikipediaGamesDb\Models\Platform;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessPlatformPageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extended_platform_fields(): void
    {

        $title = 'PlayStation 5';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Sony home video game console');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('platform wikitext');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'cover_image_url' => 'https://img/ps5.jpg',
                'release_date' => '2020-11-12',
                'website_url' => 'https://www.playstation.com/ps5/',
            ]);
        });

        (new ProcessPlatformPageJob($title))->handle($client, $parser);

        $platform = Platform::with('wikipage')->first();
        $this->assertNotNull($platform);
        $this->assertSame($title, $platform->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/PlayStation_5', $platform->wikipage->wikipedia_url);
        $this->assertSame('Sony home video game console', $platform->wikipage->description);
        $this->assertSame('platform wikitext', $platform->wikipage->wikitext);
        $this->assertSame('https://img/ps5.jpg', $platform->cover_image_url);
        $this->assertSame('2020-11-12', $platform->release_date?->toDateString());
        $this->assertSame('https://www.playstation.com/ps5/', $platform->website_url);
        $this->assertSame('PlayStation 5', $platform->name);
    }

    public function test_cover_image_fallback_used_when_missing(): void
    {

        $title = 'Nintendo Switch';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->once()->with($title)->andReturn('https://img/fallback-switch.jpg');
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Hybrid console');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'release_date' => '2017-03-03',
                'website_url' => 'https://www.nintendo.com/switch/',
                // trigger fallback for cover image
            ]);
        });

        (new ProcessPlatformPageJob($title))->handle($client, $parser);

        $platform = Platform::first();
        $this->assertSame('https://img/fallback-switch.jpg', $platform->cover_image_url);
    }

    public function test_updates_existing_platform_found_by_wikipedia_url(): void
    {
        $title = 'Xbox Series X';
        $html = '<html></html>';

        // Pre-create platform with different name but matching wikipedia_url via wikipage
        $wpId = Wikipage::create([
            'title' => 'Xbox Series X',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Xbox_Series_X',
        ])->id;
        Platform::create(['name' => 'Xbox Series X Console', 'wikipage_id' => $wpId]);

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Updated desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('updated wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'cover_image_url' => 'https://img/xbox.jpg',
                'release_date' => '2020-11-10',
                'website_url' => 'https://www.xbox.com',
            ]);
        });

        (new ProcessPlatformPageJob($title))->handle($client, $parser);

        $this->assertSame(1, Platform::count());
        $platform = Platform::first();
        $this->assertSame('Updated desc', $platform->wikipage->description);
        $this->assertSame('Xbox Series X', $platform->wikipage->title);
    }
}
