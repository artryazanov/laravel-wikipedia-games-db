<?php

namespace Tests\Models;

use Artryazanov\WikipediaGamesDb\Models\Game;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverUrlDecodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_cover_image_url_is_url_decoded_before_save(): void
    {
        $encoded = 'https://upload.wikimedia.org/wikipedia/commons/8/81/%E8%AD%A6%E5%AF%9F%E5%BA%81%E4%BB%A4%E5%92%8C%EF%BC%93%E5%B9%B4%E7%8A%AF%E7%BD%AA%E8%A2%AB%E5%AE%B3%E8%80%85%E9%80%B1%E9%96%93%E3%83%9D%E3%82%B9%E3%82%BF%E3%83%BC%E4%B8%AD%E5%B7%9D%E7%BF%94%E5%AD%90CC-BY4.0%28cropped%29.png';

        $game = Game::create([
            'clean_title' => 'Shoko Nakagawa',
            'cover_image_url' => $encoded,
        ]);

        $this->assertSame(rawurldecode($encoded), $game->cover_image_url);
        $this->assertDatabaseHas('wikipedia_games', [
            'id' => $game->id,
            'cover_image_url' => rawurldecode($encoded),
        ]);
    }
}
