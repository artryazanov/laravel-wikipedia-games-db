<?php

namespace Tests\Services;

use Artryazanov\WikipediaGamesDb\Services\InfoboxParser;
use PHPUnit\Framework\TestCase;

class InfoboxParserTest extends TestCase
{
    public function test_extracts_founded_year_and_website_url(): void
    {
        $html = <<<HTML
        <table class="infobox">
            <tr>
                <th>Founded</th>
                <td>Founded in 1991<span class="ref">[1]</span></td>
            </tr>
            <tr>
                <th>Website</th>
                <td><a href="//www.idsoftware.com">Official website</a></td>
            </tr>
        </table>
        HTML;

        $parser = new InfoboxParser();
        $data = $parser->parse($html);

        $this->assertSame(1991, $data['founded']);
        $this->assertSame('https://www.idsoftware.com', $data['website_url']);
    }

    public function test_extracts_developer_link_titles(): void
    {
        $html = <<<HTML
        <table class="infobox">
            <tr>
                <th>Developer(s)</th>
                <td>
                    <a href="/wiki/Id_Software" title="Id Software">id Software</a>,
                    <a href="/wiki/GT_Interactive" title="GT Interactive">GT Interactive</a>
                </td>
            </tr>
        </table>
        HTML;

        $parser = new InfoboxParser();
        $data = $parser->parse($html);

        $this->assertArrayHasKey('developers_link_titles', $data);
        $this->assertEqualsCanonicalizing(['Id Software', 'GT Interactive'], $data['developers_link_titles']);
        $this->assertEqualsCanonicalizing(['id Software', 'GT Interactive'], $data['developers']);
    }

    public function test_extracts_platform_link_titles(): void
    {
        $html = <<<HTML
        <table class="infobox">
            <tr>
                <th>Platforms</th>
                <td>
                    <a href="/wiki/Microsoft_Windows" title="Microsoft Windows">Windows</a>,
                    <a href="/wiki/PlayStation_5" title="PlayStation 5">PlayStation 5</a>
                </td>
            </tr>
        </table>
        HTML;

        $parser = new InfoboxParser();
        $data = $parser->parse($html);

        $this->assertArrayHasKey('platforms_link_titles', $data);
        $this->assertEqualsCanonicalizing(['Microsoft Windows', 'PlayStation 5'], $data['platforms_link_titles']);
        $this->assertEqualsCanonicalizing(['Windows', 'PlayStation 5'], $data['platforms']);
    }

    public function test_extracts_genre_link_titles(): void
    {
        $html = <<<HTML
        <table class="infobox">
            <tr>
                <th>Genres</th>
                <td>
                    <a href="/wiki/Shooter_(video_games)" title="Shooter (video games)">Shooter</a>,
                    <a href="/wiki/Role-playing_video_game" title="Role-playing video game">RPG</a>
                </td>
            </tr>
        </table>
        HTML;

        $parser = new InfoboxParser();
        $data = $parser->parse($html);

        $this->assertArrayHasKey('genres_link_titles', $data);
        $this->assertEqualsCanonicalizing(['Shooter (video games)', 'Role-playing video game'], $data['genres_link_titles']);
        $this->assertEqualsCanonicalizing(['Shooter', 'RPG'], $data['genres']);
    }
    public function test_extracts_mode_link_titles(): void
    {
        $html = <<<HTML
        <table class="infobox">
            <tr>
                <th>Modes</th>
                <td>
                    <a href="/wiki/Single-player_video_game" title="Single-player video game">Single-player</a>,
                    <a href="/wiki/Multiplayer_video_game" title="Multiplayer video game">Multiplayer</a>
                </td>
            </tr>
        </table>
        HTML;

        $parser = new InfoboxParser();
        $data = $parser->parse($html);

        $this->assertArrayHasKey('modes_link_titles', $data);
        $this->assertEqualsCanonicalizing(['Single-player video game', 'Multiplayer video game'], $data['modes_link_titles']);
        $this->assertEqualsCanonicalizing(['Single-player', 'Multiplayer'], $data['modes']);
    }

    public function test_extracts_series_link_titles(): void
    {
        $html = <<<HTML
        <table class="infobox">
            <tr>
                <th>Series</th>
                <td>
                    <a href="/wiki/The_Legend_of_Zelda" title="The Legend of Zelda">The Legend of Zelda</a>,
                    <a href="/wiki/Mario_(franchise)" title="Mario (franchise)">Mario</a>
                </td>
            </tr>
        </table>
        HTML;

        $parser = new InfoboxParser();
        $data = $parser->parse($html);

        $this->assertArrayHasKey('series_link_titles', $data);
        $this->assertEqualsCanonicalizing(['The Legend of Zelda', 'Mario (franchise)'], $data['series_link_titles']);
        $this->assertEqualsCanonicalizing(['The Legend of Zelda', 'Mario'], $data['series']);
    }
}
