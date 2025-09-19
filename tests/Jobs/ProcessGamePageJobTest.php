<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessGamePageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_game_and_relations_from_parsed_data(): void
    {

        $title = 'Doom (1993 video game)';
        $html = '<html></html>';

        // Mock MediaWikiClient
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            // cover_image_url provided by parser -> fallback should not be called, but we can allow it not to be expected
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wikitext sample');
        });

        // Mock InfoboxParser
        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'description' => 'Parser desc',
                'cover_image_url' => 'https://img/cover.jpg',
                'release_date' => '1993-12-10',
                'genres' => ['Shooter', 'FPS'],
                'platforms' => ['MS-DOS', 'Windows'],
                'modes' => ['Single-player', 'Multiplayer'],
                'series' => ['Doom'],
                'engines' => ['id Tech 1'],
                'developers' => ['id Software'],
                'publishers' => ['GT Interactive'],
            ]);
        });

        // Execute job
        (new ProcessGamePageJob($title))->handle($client, $parser);

        $game = Game::with('wikipage')->first();
        $this->assertNotNull($game);
        $this->assertSame($title, $game->wikipage->title);
        $this->assertSame('Doom', $game->clean_title);
        $this->assertSame('https://en.wikipedia.org/wiki/Doom_(1993_video_game)', $game->wikipage->wikipedia_url);
        $this->assertSame('Lead desc', $game->wikipage->description); // lead has priority
        $this->assertSame('wikitext sample', $game->wikipage->wikitext);
        $this->assertSame('https://img/cover.jpg', $game->cover_image_url);
        $this->assertSame('1993-12-10', $game->release_date->toDateString());
        $this->assertSame(1993, $game->release_year);

        // Relations
        $this->assertEqualsCanonicalizing(['Shooter', 'FPS'], $game->genres()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['MS-DOS', 'Windows'], $game->platforms()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Single-player', 'Multiplayer'], $game->modes()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['Doom'], $game->series()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['id Tech 1'], $game->engines()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['id Software'], $game->developers()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['GT Interactive'], $game->publishers()->pluck('name')->all());
    }

    public function test_cover_image_fallback_used_when_missing(): void
    {

        $title = 'Some Game';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->once()->with($title)->andReturn('https://img/fallback.jpg');
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn(null);
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'release_date' => '2001-01-01',
                // No cover_image_url here to trigger fallback
                // Include at least one company so a new game is created
                'developers' => ['Fallback Studios'],
                'publishers' => ['Fallback Publishing'],
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $game = Game::first();
        $this->assertSame('https://img/fallback.jpg', $game->cover_image_url);
    }

    public function test_no_db_writes_when_parser_returns_empty(): void
    {

        $title = 'Empty Page';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $this->assertSame(0, Game::count());
    }
}
