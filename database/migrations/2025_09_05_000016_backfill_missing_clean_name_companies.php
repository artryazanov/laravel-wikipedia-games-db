<?php

use Artryazanov\WikipediaGamesDb\Support\Concerns\CleansTitles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CleansTitles;

    public function up(): void
    {
        if (! Schema::hasTable('wikipedia_game_companies') || ! Schema::hasColumn('wikipedia_game_companies', 'clean_name')) {
            return;
        }

        DB::table('wikipedia_game_companies')
            ->whereNull('clean_name')
            ->orWhere('clean_name', '=','')
            ->orderBy('id')
            ->select('id', 'name')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $name = is_string($row->name ?? null) ? trim($row->name) : '';
                    if ($name === '') {
                        continue;
                    }
                    $clean = $this->makeCleanTitle($name);
                    DB::table('wikipedia_game_companies')
                        ->where('id', $row->id)
                        ->update(['clean_name' => $clean]);
                }
            });
    }

    public function down(): void
    {
        // No-op: data backfill is not safely reversible.
    }
};

