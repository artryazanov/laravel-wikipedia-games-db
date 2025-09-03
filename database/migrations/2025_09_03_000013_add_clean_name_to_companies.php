<?php

use Artryazanov\WikipediaGamesDb\Support\Concerns\CleansTitles;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    use CleansTitles;

    public function up(): void
    {
        if (Schema::hasTable('wikipedia_game_companies')) {
            Schema::table('wikipedia_game_companies', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_companies', 'clean_name')) {
                    $table->string('clean_name')->index()->nullable()->after('name')->comment('Normalized company name without disambiguation.');
                }
            });

            // Backfill clean_name from name using the same cleaning logic
            DB::table('wikipedia_game_companies')
                ->orderBy('id')
                ->select('id', 'name')
                ->chunkById(500, function ($rows) {
                    foreach ($rows as $row) {
                        $name = is_string($row->name ?? null) ? $row->name : null;
                        if ($name === null) {
                            continue;
                        }
                        $clean = $this->makeCleanTitle($name);
                        DB::table('wikipedia_game_companies')->where('id', $row->id)->update([
                            'clean_name' => $clean,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('wikipedia_game_companies') && Schema::hasColumn('wikipedia_game_companies', 'clean_name')) {
            Schema::table('wikipedia_game_companies', function (Blueprint $table) {
                $table->dropColumn('clean_name');
            });
        }
    }
};
