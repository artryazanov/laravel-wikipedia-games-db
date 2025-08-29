<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessSeriesPageJob;
use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
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

        $series = Series::with('wikipage')->first();
        $this->assertNotNull($series);
        $this->assertSame($title, $series->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/The_Legend_of_Zelda', $series->wikipage->wikipedia_url);
        $this->assertSame('Nintendo action-adventure franchise', $series->wikipage->description);
        $this->assertSame('series wikitext', $series->wikipage->wikitext);
        $this->assertSame('The Legend of Zelda', $series->name);
    }

    public function test_updates_existing_series_found_by_wikipedia_url(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);
        $title = 'Mario (franchise)';
        $html = '<html></html>';

        // Pre-create series with different name but matching wikipedia_url via wikipage
        $wpId = Wikipage::create([
            'title' => 'Mario (franchise)',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Mario_(franchise)',
        ])->id;
        Series::create(['name' => 'Mario', 'wikipage_id' => $wpId]);

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
        $this->assertSame('Updated series desc', $series->wikipage->description);
        $this->assertSame('Mario (franchise)', $series->wikipage->title);
    }
}
