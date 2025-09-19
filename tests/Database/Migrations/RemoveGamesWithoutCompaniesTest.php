<?php

namespace Tests\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RemoveGamesWithoutCompaniesTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_games_without_companies_and_orphan_wikipages(): void
    {
        // Create a Wikipage and a Game referencing it, with no companies
        $wikipageId = DB::table('wikipedia_game_wikipages')->insertGetId([
            'title' => 'Orphan Game',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Orphan_Game',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $gameId = DB::table('wikipedia_games')->insertGetId([
            'wikipage_id' => $wikipageId,
            'clean_title' => 'Orphan Game',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Sanity preconditions
        $this->assertDatabaseHas('wikipedia_games', ['id' => $gameId]);
        $this->assertDatabaseHas('wikipedia_game_wikipages', ['id' => $wikipageId]);

        // Run the migration
        $migration = require __DIR__.'/../../../database/migrations/2025_09_19_000018_remove_games_without_companies_and_orphan_wikipages.php';
        $migration->up();

        // Game is deleted, and its wikipage is deleted as orphan
        $this->assertDatabaseMissing('wikipedia_games', ['id' => $gameId]);
        $this->assertDatabaseMissing('wikipedia_game_wikipages', ['id' => $wikipageId]);
    }

    public function test_keeps_wikipage_if_still_referenced_elsewhere(): void
    {
        // Create a shared Wikipage
        $wikipageId = DB::table('wikipedia_game_wikipages')->insertGetId([
            'title' => 'Shared Page',
            'wikipedia_url' => 'https://en.wikipedia.org/wiki/Shared_Page',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a game referencing it (no companies)
        $gameId = DB::table('wikipedia_games')->insertGetId([
            'wikipage_id' => $wikipageId,
            'clean_title' => 'Some Game',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Create a company that also references the same wikipage
        DB::table('wikipedia_game_companies')->insert([
            'name' => 'Some Studio',
            'clean_name' => 'Some Studio',
            'wikipage_id' => $wikipageId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Run the migration
        $migration = require __DIR__.'/../../../database/migrations/2025_09_19_000018_remove_games_without_companies_and_orphan_wikipages.php';
        $migration->up();

        // Game is deleted
        $this->assertDatabaseMissing('wikipedia_games', ['id' => $gameId]);
        // Wikipage remains because it's referenced by the company
        $this->assertDatabaseHas('wikipedia_game_wikipages', ['id' => $wikipageId]);
    }
}

