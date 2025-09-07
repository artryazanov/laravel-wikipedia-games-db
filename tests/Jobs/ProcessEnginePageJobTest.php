<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessEnginePageJob;
use Artryazanov\WikipediaGamesDb\Models\Engine;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessEnginePageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extended_engine_fields(): void
    {

        $title = 'Unreal Engine 3';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Epic Games engine');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('engine wikitext');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'cover_image_url' => 'https://img/engine.jpg',
                'release_date' => '2005-06-01',
                'website_url' => 'https://www.unrealengine.com',
            ]);
        });

        (new ProcessEnginePageJob($title))->handle($client, $parser);

        $engine = Engine::with('wikipage')->first();
        $this->assertNotNull($engine);
        $this->assertSame($title, $engine->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/Unreal_Engine_3', $engine->wikipage->wikipedia_url);
        $this->assertSame('Epic Games engine', $engine->wikipage->description);
        $this->assertSame('engine wikitext', $engine->wikipage->wikitext);
        $this->assertSame('https://img/engine.jpg', $engine->cover_image_url);
        $this->assertSame('2005-06-01', $engine->release_date?->toDateString());
        $this->assertSame('https://www.unrealengine.com', $engine->website_url);
        $this->assertSame('Unreal Engine 3', $engine->name);
    }

    public function test_cover_image_fallback_used_when_missing(): void
    {

        $title = 'Unity (game engine)';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->once()->with($title)->andReturn('https://img/fallback-engine.jpg');
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Engine lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'release_date' => '2005-06-08',
                'website_url' => 'https://unity.com',
            ]);
        });

        (new ProcessEnginePageJob($title))->handle($client, $parser);

        $engine = Engine::first();
        $this->assertSame('https://img/fallback-engine.jpg', $engine->cover_image_url);
    }

    public function test_updates_existing_engine_found_by_wikipedia_url(): void
    {
        $title = 'Unreal Engine 3';
        $html = '<html></html>';

        // Pre-create engine with different name but matching wikipedia_url via wikipage
        $wpId = Wikipage::create([
            'title' => 'Unreal Engine 3',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Unreal_Engine_3',
        ])->id;
        Engine::create(['name' => 'UE3', 'wikipage_id' => $wpId]);

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Updated engine desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('updated wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'cover_image_url' => 'https://img/engine2.jpg',
                'release_date' => '2006-01-01',
                'website_url' => 'https://www.unrealengine.com',
            ]);
        });

        (new ProcessEnginePageJob($title))->handle($client, $parser);

        $this->assertSame(1, Engine::count());
        $engine = Engine::first();
        $this->assertSame('Updated engine desc', $engine->wikipage->description);
        $this->assertSame('Unreal Engine 3', $engine->wikipage->title);
    }
}
