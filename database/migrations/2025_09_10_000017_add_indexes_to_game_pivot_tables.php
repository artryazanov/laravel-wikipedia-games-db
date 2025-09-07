<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'wikipedia_game_game_genre' => ['genre_id'],
            'wikipedia_game_game_platform' => ['platform_id'],
            'wikipedia_game_game_mode' => ['mode_id'],
            'wikipedia_game_game_series' => ['series_id'],
            'wikipedia_game_game_engine' => ['engine_id'],
            'wikipedia_game_game_company' => ['company_id', 'role'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        $tables = [
            'wikipedia_game_game_genre' => ['genre_id'],
            'wikipedia_game_game_platform' => ['platform_id'],
            'wikipedia_game_game_mode' => ['mode_id'],
            'wikipedia_game_game_series' => ['series_id'],
            'wikipedia_game_game_engine' => ['engine_id'],
            'wikipedia_game_game_company' => ['company_id', 'role'],
        ];

        foreach ($tables as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            Schema::table($table, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->dropIndex([$column]);
                }
            });
        }
    }
};
