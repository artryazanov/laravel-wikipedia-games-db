<?php

namespace Tests\Jobs;

use Artryazanov\WikipediaGamesDb\Jobs\ProcessGamePageJob;
use Artryazanov\WikipediaGamesDb\Models\Game;
use Artryazanov\WikipediaGamesDb\Models\Wikipage;
use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use Artryazanov\WikipediaGamesDb\Services\MediaWikiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MultiInfoboxJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The job should parse multiple infoboxes from one HTML page and create multiple Game records
     * that all reference the same Wikipage record.
     */
    public function test_multiple_infoboxes_create_multiple_games_sharing_wikipage(): void
    {
        $title = 'Ni no Kuni mobile games';

        $html = <<<'HTML'
<!doctype html>
<html>
<body>
  <table class="infobox ib-video-game hproduct">
    <tr><th class="infobox-above fn"><i>Game One</i></th></tr>
    <tr><td><a class="image"><img src="//img1.example.com/cover1.jpg"></a></td></tr>
    <tr><th>Developer(s)</th><td><a href="/wiki/Dev_One">Dev One</a></td></tr>
    <tr><th>Publisher(s)</th><td><a href="/wiki/Pub_One">Pub One</a></td></tr>
    <tr><th>Genre(s)</th><td><a href="/wiki/Action_game">Action</a></td></tr>
    <tr><th>Release</th><td>March 7, 2013</td></tr>
  </table>

  <table class="infobox ib-video-game hproduct">
    <tr><th class="infobox-above fn"><i>Game Two</i></th></tr>
    <tr><td><a class="image"><img src="https://img2.example.com/cover2.jpg"></a></td></tr>
    <tr><th>Developer(s)</th><td><a href="/wiki/Dev_Two">Dev Two</a></td></tr>
    <tr><th>Publisher(s)</th><td><a href="/wiki/Pub_Two">Pub Two</a></td></tr>
    <tr><th>Genre(s)</th><td><a href="/wiki/Role-playing_video_game">Role-playing</a></td></tr>
    <tr><th>Release</th><td>2015</td></tr>
  </table>
</body>
</html>
HTML;

        // Ensure nested dispatched jobs (companies/platforms/etc.) don't execute inline
        // to keep this test focused on multi-infobox parsing and persistence.
        Bus::fake();

        // Mock MediaWikiClient, but use real InfoboxParser to exercise parseAll()
        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('isDisambiguation')->andReturn(false);
            $mock->shouldReceive('isRedirect')->andReturn(false);
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead for multi');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('Wikitext multi');
        });

        $parser = new InfoboxParser;

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $games = Game::with('wikipage', 'developers', 'publishers', 'genres')->orderBy('id')->get();
        $this->assertCount(2, $games);

        // One Wikipage shared by both
        $this->assertSame(1, Wikipage::count());
        $this->assertNotNull($games[0]->wikipage);
        $this->assertSame($games[0]->wikipage_id, $games[1]->wikipage_id);
        $this->assertSame($title, $games[0]->wikipage->title);
        $this->assertSame('https://en.wikipedia.org/wiki/Ni_no_Kuni_mobile_games', $games[0]->wikipage->wikipedia_url);

        // Clean titles taken from infobox headers
        $this->assertSame('Game One', $games[0]->clean_title);
        $this->assertSame('Game Two', $games[1]->clean_title);

        // Release years parsed per infobox
        $this->assertSame(2013, $games[0]->release_year);
        $this->assertSame(2015, $games[1]->release_year);

        // Cover images extracted from each infobox ("//" becomes https)
        $this->assertSame('https://img1.example.com/cover1.jpg', $games[0]->cover_image_url);
        $this->assertSame('https://img2.example.com/cover2.jpg', $games[1]->cover_image_url);

        // Basic relations persisted per game
        $this->assertEquals(['Action'], $games[0]->genres->pluck('name')->all());
        $this->assertEquals(['Role-playing'], $games[1]->genres->pluck('name')->all());
        $this->assertEquals(['Dev One'], $games[0]->developers->pluck('name')->all());
        $this->assertEquals(['Pub One'], $games[0]->publishers->pluck('name')->all());
        $this->assertEquals(['Dev Two'], $games[1]->developers->pluck('name')->all());
        $this->assertEquals(['Pub Two'], $games[1]->publishers->pluck('name')->all());
    }

    /**
     * If one of the infoboxes lacks required fields (developers, publishers, genres, release year),
     * it should be skipped while valid ones are persisted.
     */
    public function test_skips_infobox_without_required_fields(): void
    {
        $title = 'Mixed infobox page';

        $html = <<<'HTML'
<!doctype html>
<html>
<body>
  <!-- Valid -->
  <table class="infobox">
    <tr><th class="infobox-above fn">Valid Game</th></tr>
    <tr><th>Developer(s)</th><td>Team A</td></tr>
    <tr><th>Publisher(s)</th><td>Pub A</td></tr>
    <tr><th>Genre(s)</th><td>Action</td></tr>
    <tr><th>Release</th><td>2019</td></tr>
  </table>

  <!-- Invalid: missing publishers -->
  <table class="infobox">
    <tr><th class="infobox-above fn">Invalid Game</th></tr>
    <tr><th>Developer(s)</th><td>Team B</td></tr>
    <tr><th>Genre(s)</th><td>Adventure</td></tr>
    <tr><th>Release</th><td>2018</td></tr>
  </table>
</body>
</html>
HTML;

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('isDisambiguation')->andReturn(false);
            $mock->shouldReceive('isRedirect')->andReturn(false);
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn(null);
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT');
        });

        $parser = new InfoboxParser;

        (new ProcessGamePageJob($title))->handle($client, $parser);

        $this->assertSame(1, Game::count());
        $game = Game::first();
        $this->assertSame('Valid Game', $game->clean_title);
        $this->assertSame(2019, $game->release_year);
        $this->assertEquals(['Action'], $game->genres()->pluck('name')->all());
    }

    /**
     * Running the same job twice for the same page should not create duplicate games;
     * upsert is by (wikipage_id, clean_title).
     */
    public function test_idempotent_upsert_by_clean_title_within_same_wikipage(): void
    {
        $title = 'Two games page';

        $html = <<<'HTML'
<!doctype html>
<html>
<body>
  <table class="infobox">
    <tr><th class="infobox-above fn">Foo Game</th></tr>
    <tr><th>Developer(s)</th><td>Dev X</td></tr>
    <tr><th>Publisher(s)</th><td>Pub X</td></tr>
    <tr><th>Genre(s)</th><td>Action</td></tr>
    <tr><th>Release</th><td>2020</td></tr>
  </table>
  <table class="infobox">
    <tr><th class="infobox-above fn">Bar Game</th></tr>
    <tr><th>Developer(s)</th><td>Dev Y</td></tr>
    <tr><th>Publisher(s)</th><td>Pub Y</td></tr>
    <tr><th>Genre(s)</th><td>Strategy</td></tr>
    <tr><th>Release</th><td>2021</td></tr>
  </table>
</body>
</html>
HTML;

        $client = $this->mock(MediaWikiClient::class, function ($mock) use ($title, $html) {
            $mock->shouldReceive('isDisambiguation')->andReturn(false);
            $mock->shouldReceive('isRedirect')->andReturn(false);
            // First run
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead once');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT once');
            // Second run
            $mock->shouldReceive('getPageHtml')->once()->with($title)->andReturn($html);
            $mock->shouldReceive('getPageLeadDescription')->once()->with($title)->andReturn('Lead twice');
            $mock->shouldReceive('getPageWikitext')->once()->with($title)->andReturn('WT twice');
        });

        $parser = new InfoboxParser;

        (new ProcessGamePageJob($title))->handle($client, $parser);
        (new ProcessGamePageJob($title))->handle($client, $parser);

        $this->assertSame(2, Game::count());
        $this->assertSame(1, Wikipage::count());
        $this->assertEqualsCanonicalizing(['Foo Game', 'Bar Game'], Game::pluck('clean_title')->all());
    }
}
