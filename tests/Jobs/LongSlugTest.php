<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LongSlugTest extends TestCase
{
    use RefreshDatabase;

    public function test_extremely_long_taxonomy_names_do_not_overflow_slug_column(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        $title = 'Very Long Slug Game';
        $html = '<html></html>';

        $longName = str_repeat('Very Long Name Segment ', 30); // > 600 chars before slugging

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) use ($longName) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'description' => 'Desc',
                'release_date' => '2000-01-01',
                'genres' => [$longName],
                                'cover_image_url' => 'https://example/img.jpg',
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $game = Game::first();
        $this->assertNotNull($game);
        $this->assertSame('Lead', $game->description);
        // Ensure the related genre record exists and queryable via relationship
        $this->assertSame(1, $game->genres()->count());
    }
}
