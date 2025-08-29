<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessCompanyPageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessEnginePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessGenrePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessModePageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessPlatformPageJob;
use Artryazanov\WikipediaGamesDb\Jobs\ProcessSeriesPageJob;
use Artryazanov\WikipediaGamesDb\Models\Company;
use Artryazanov\WikipediaGamesDb\Models\Engine;
use Artryazanov\WikipediaGamesDb\Models\Genre;
use Artryazanov\WikipediaGamesDb\Models\Mode;
use Artryazanov\WikipediaGamesDb\Models\Platform;
use Artryazanov\WikipediaGamesDb\Models\Series;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ProcessGamePageJobConditionalDispatchSkipTest extends TestCase
{
    use RefreshDatabase;

    public function test_does_not_dispatch_taxonomy_jobs_when_records_exist_with_wikipedia_url(): void
    {
        config()->set('game-scraper.throttle_milliseconds', 0);

        // Pre-create records that match link titles and already have wikipedia_url filled
        // Companies
        Company::create(['name' => 'Id Software', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Id_Software']);
        Company::create(['name' => 'GT Interactive', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/GT_Interactive']);

        // Platforms
        Platform::create(['name' => 'Microsoft Windows', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Microsoft_Windows']);
        Platform::create(['name' => 'PlayStation 5', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/PlayStation_5']);

        // Engines
        Engine::create(['name' => 'Unreal Engine', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Unreal_Engine']);
        Engine::create(['name' => 'Unity (game engine)', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Unity_(game_engine)']);

        // Genres
        Genre::create(['name' => 'Shooter (video games)', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Shooter_(video_games)']);
        Genre::create(['name' => 'Role-playing video game', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Role-playing_video_game']);

        // Modes
        Mode::create(['name' => 'Single-player video game', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Single-player_video_game']);
        Mode::create(['name' => 'Multiplayer video game', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Multiplayer_video_game']);

        // Series
        Series::create(['name' => 'The Legend of Zelda', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/The_Legend_of_Zelda']);
        Series::create(['name' => 'Mario (franchise)', 'wikipedia_url' => 'https://en.wikipedia.org/wiki/Mario_(franchise)']);

        $title = 'Game With Links Already Known';
        $html = '<html></html>';

        Bus::fake();

        // Mock MediaWikiClient
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageMainImage')->andReturn(null);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        // Mock InfoboxParser to include *_link_titles
        $parser = $this->mock(InfoboxParser::class, function ($mock) {
            $mock->shouldReceive('parse')->once()->andReturn([
                'developers_link_titles' => ['Id Software'],
                'publishers_link_titles' => ['GT Interactive'],
                'platforms_link_titles' => ['Microsoft Windows', 'PlayStation 5'],
                'engines_link_titles' => ['Unreal Engine', 'Unity (game engine)'],
                'genres_link_titles' => ['Shooter (video games)', 'Role-playing video game'],
                'modes_link_titles' => ['Single-player video game', 'Multiplayer video game'],
                'series_link_titles' => ['The Legend of Zelda', 'Mario (franchise)'],
                // minimal other data so job continues
                'release_date' => '2000-01-01',
            ]);
        });

        (new ProcessGamePageJob($title))->handle($client, $parser);

        Bus::assertNotDispatched(ProcessCompanyPageJob::class);
        Bus::assertNotDispatched(ProcessPlatformPageJob::class);
        Bus::assertNotDispatched(ProcessEnginePageJob::class);
        Bus::assertNotDispatched(ProcessGenrePageJob::class);
        Bus::assertNotDispatched(ProcessModePageJob::class);
        Bus::assertNotDispatched(ProcessSeriesPageJob::class);
    }
}
