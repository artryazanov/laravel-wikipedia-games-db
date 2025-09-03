<?php

namespace Tests\Database\Migrations;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CleanNameAndFootnoteCleanupTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_clean_name_migration_backfills_from_name(): void
    {
        // Insert companies with names that need cleaning and ensure clean_name is null initially
        DB::table('wikipedia_game_companies')->insert([
            ['name' => 'Valve (company)', 'clean_name' => null],
            ['name' => 'Nintendo   ', 'clean_name' => null],
        ]);

        // Run the migration's up() to trigger backfill logic
        $migration = require __DIR__.'/../../../database/migrations/2025_09_03_000013_add_clean_name_to_companies.php';
        $migration->up();

        $rows = DB::table('wikipedia_game_companies')->orderBy('id')->get()->all();
        $this->assertSame('Valve', $rows[0]->clean_name);
        // Collapses and trims whitespace
        $this->assertSame('Nintendo', $rows[1]->clean_name);
    }

    public function test_remove_bracket_footnote_companies_migration_deletes_tokens(): void
    {
        DB::table('wikipedia_game_companies')->insert([
            ['name' => '[a]'],
            ['name' => 'Capcom'],
            ['name' => '[1]'],
            ['name' => 'Epic Games'],
        ]);

        $migration = require __DIR__.'/../../../database/migrations/2025_09_03_000014_remove_bracket_footnote_companies.php';
        $migration->up();

        $this->assertDatabaseMissing('wikipedia_game_companies', ['name' => '[a]']);
        $this->assertDatabaseMissing('wikipedia_game_companies', ['name' => '[1]']);
        $this->assertDatabaseHas('wikipedia_game_companies', ['name' => 'Capcom']);
        $this->assertDatabaseHas('wikipedia_game_companies', ['name' => 'Epic Games']);
    }
}
