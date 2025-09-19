<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessGamePageJobSkipCreationWithoutCompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_create_wikipage_or_game_without_companies(): void
    {
        $title = 'Game Without Companies';
        $html = '<html></html>';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        // Parser returns no developers/publishers
        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'release_date' => '2000-01-01',
                'genres' => ['Shooter'],
                // No developers or publishers keys
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $this->assertSame(0, Game::count());
        $this->assertSame(0, Wikipage::count());
    }
}

