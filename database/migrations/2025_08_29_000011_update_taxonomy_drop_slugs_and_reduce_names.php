<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Drop slug columns from taxonomy tables if present
        $tables = [
            'wikipedia_game_companies',
            'wikipedia_game_engines',
            'wikipedia_game_genres',
            'wikipedia_game_modes',
            'wikipedia_game_platforms',
            'wikipedia_game_series',
        ];

        foreach ($tables as $tableName) {
            if (! Schema::hasTable($tableName) || ! Schema::hasColumn($tableName, 'slug')) {
                continue;
            }

            $driver = Schema::getConnection()->getDriverName();
            // For SQLite, drop the unique index explicitly before dropping the column
            if ($driver === 'sqlite') {
                $indexName = $tableName . '_slug_unique';
                try {
                    DB::statement('DROP INDEX IF EXISTS "'.$indexName.'"');
                } catch (\Throwable $e) {
                    // ignore; best-effort for SQLite index drop
                }
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('slug');
            });
        }

        // 2) Reduce name length for companies and platforms back to 255
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql'])) {
            // Remove rows that exceed new max length to avoid migration failure
            if (Schema::hasTable('wikipedia_game_companies')) {
                DB::statement('DELETE FROM `wikipedia_game_companies` WHERE CHAR_LENGTH(`name`) > 255');
                DB::statement('ALTER TABLE `wikipedia_game_companies` MODIFY COLUMN `name` VARCHAR(255) NOT NULL');
            }
            if (Schema::hasTable('wikipedia_game_platforms')) {
                DB::statement('DELETE FROM `wikipedia_game_platforms` WHERE CHAR_LENGTH(`name`) > 255');
                DB::statement('ALTER TABLE `wikipedia_game_platforms` MODIFY COLUMN `name` VARCHAR(255) NOT NULL');
            }
        }
    }

    public function down(): void
    {
        // Recreate slug columns as unique strings after name
        $tables = [
            'wikipedia_game_companies',
            'wikipedia_game_engines',
            'wikipedia_game_genres',
            'wikipedia_game_modes',
            'wikipedia_game_platforms',
            'wikipedia_game_series',
        ];

        foreach ($tables as $tableName) {
            if (Schema::hasTable($tableName) && ! Schema::hasColumn($tableName, 'slug')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->string('slug')->unique()->after('name');
                });
            }
        }

        // Restore name length to 512 to align with earlier migration
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql'])) {
            if (Schema::hasTable('wikipedia_game_companies')) {
                DB::statement('ALTER TABLE `wikipedia_game_companies` MODIFY COLUMN `name` VARCHAR(512) NOT NULL');
            }
            if (Schema::hasTable('wikipedia_game_platforms')) {
                DB::statement('ALTER TABLE `wikipedia_game_platforms` MODIFY COLUMN `name` VARCHAR(512) NOT NULL');
            }
        }
    }
};
