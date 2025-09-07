<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Genre;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessGamePageJobReusesExistingTaxonomiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_reuses_existing_taxonomies_and_inserts_missing_only_once(): void
    {
        // Precreate a genre that should be reused
        Genre::create(['name' => 'Shooter']);

        $title = 'Game With Existing Genre';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->twice()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->twice()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->twice()->with($title)->andReturn('WT');
            $mock->shouldReceive('isDisambiguation')->andReturnFalse();
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->twice()->andReturn([
                'release_date' => '2000-01-01',
                'genres' => ['Shooter', 'RPG'],
            ]);
        });

        // Run the job twice with the same data
        (new ProcessGamePageJob($title))->handle($client, $parser);
        (new ProcessGamePageJob($title))->handle($client, $parser);

        $this->assertSame(2, Genre::count());
        $game = Game::with('genres')->first();
        $this->assertEqualsCanonicalizing(['Shooter', 'RPG'], $game->genres->pluck('name')->all());
    }
}

