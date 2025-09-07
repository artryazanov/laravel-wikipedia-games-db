<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGenrePageJob;
use Artryazanov\WikipediaGamesDb\Models\Genre;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessGenrePageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extended_genre_fields(): void
    {

        $title = 'Shooter (video games)';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Genre lead description');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('genre wikitext');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                // genres typically won't have cover images; we only need description/wikitext
            ]);
        });

        (new ProcessGenrePageJob($title))->handle($client, $parser);

        $genre = Genre::with('wikipage')->first();
        $this->assertNotNull($genre);
        $this->assertSame($title, $genre->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/Shooter_(video_games)', $genre->wikipage->wikipedia_url);
        $this->assertSame('Genre lead description', $genre->wikipage->description);
        $this->assertSame('genre wikitext', $genre->wikipage->wikitext);
        $this->assertSame('Shooter (video games)', $genre->name);
    }

    public function test_updates_existing_genre_found_by_wikipedia_url(): void
    {
        $title = 'Shooter (video games)';
        $html = '<html></html>';

        // Pre-create genre with different name but matching wikipedia_url via wikipage
        $wpId = Wikipage::create([
            'title' => 'Shooter (video games)',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Shooter_(video_games)',
        ])->id;
        Genre::create(['name' => 'Shooter', 'wikipage_id' => $wpId]);

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Updated genre desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('updated wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([]);
        });

        (new ProcessGenrePageJob($title))->handle($client, $parser);

        $this->assertSame(1, Genre::count());
        $genre = Genre::first();
        $this->assertSame('Updated genre desc', $genre->wikipage->description);
        $this->assertSame('Shooter (video games)', $genre->wikipage->title);
    }
}
