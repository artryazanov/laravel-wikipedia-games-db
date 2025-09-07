<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessGamePageJobDisambiguationTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_processing_when_page_is_disambiguation(): void
    {

        $title = 'Some Ambiguous Title';

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title) {
            $mock->shouldReceive('isDisambiguation')->once()->with($title)->andReturn(true);
            $mock->shouldReceive('getPageHtml')->never();
        });

        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->never();
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $this->assertSame(0, Game::count());
    }
}
