<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessSeriesPageJob;
use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessSeriesPageJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_extended_series_fields(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'The Legend of Zelda';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Nintendo action-adventure franchise');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('series wikitext');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([]);
        });

        (new ProcessSeriesPageJob($title))->handle($client, $parser);

        $series = Series::first();
        $this->assertNotNull($series);
        $this->assertSame($title, $series->title);
        $this->assertSame('https://en.wikipedia.org/wiki/The_Legend_of_Zelda', $series->wikipedia_url);
        $this->assertSame('Nintendo action-adventure franchise', $series->description);
        $this->assertSame('series wikitext', $series->wikitext);
        $this->assertSame('The Legend of Zelda', $series->name);
        $this->assertSame('the-legend-of-zelda', $series->slug);
    }

    public function test_updates_existing_series_found_by_slug(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);
        $title = 'Mario (franchise)';
        $html = '<html></html>';

        // Pre-create series with different name but matching slug derived from title
        Series::create(['name' => 'Mario', 'slug' => 'mario-franchise']);

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Updated series desc');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('updated wt');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([]);
        });

        (new ProcessSeriesPageJob($title))->handle($client, $parser);

        $this->assertSame(1, Series::count());
        $series = Series::first();
        $this->assertSame('Updated series desc', $series->description);
        $this->assertSame('Mario (franchise)', $series->title);
    }
}
