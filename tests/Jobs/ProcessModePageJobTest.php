<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessModePageJob;
use Artryazanov\WikipediaGamesDb\Models\Mode;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessModePageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extended_mode_fields(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'Single-player video game';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Mode lead description');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('mode wikitext');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([]);
        });

        (new ProcessModePageJob($title))->handle($client, $parser);

        $mode = Mode::with('wikipage')->first();
        $this->assertNotNull($mode);
        $this->assertSame($title, $mode->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/Single-player_video_game', $mode->wikipage->wikipedia_url);
        $this->assertSame('Mode lead description', $mode->wikipage->description);
        $this->assertSame('mode wikitext', $mode->wikipage->wikitext);
        $this->assertSame('Single-player video game', $mode->name);
    }

    public function test_updates_existing_mode_found_by_wikipedia_url(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);
        $title = 'Single-player video game';
        $html = '<html></html>';

        // Pre-create mode with different name but matching wikipedia_url via wikipage
        $wpId = Wikipage::create([
            'title' => 'Single-player video game',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Single-player_video_game',
        ])->id;
        Mode::create(['name' => 'Single player', 'wikipage_id' => $wpId]);

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Updated mode desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('updated wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([]);
        });

        (new ProcessModePageJob($title))->handle($client, $parser);

        $this->assertSame(1, Mode::count());
        $mode = Mode::first();
        $this->assertSame('Updated mode desc', $mode->wikipage->description);
        $this->assertSame('Single-player video game', $mode->wikipage->title);
    }
}
