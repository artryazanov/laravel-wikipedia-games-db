<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wikipedia_game_companies')) {
            return;
        }

        // Delete rows where name is exactly a bracket footnote token like "[a]" or "[1]".
        DB::table('wikipedia_game_companies')
            ->orderBy('id')
            ->select('id', 'name')
            ->chunkById(500, function ($rows) {
                $idsToDelete = [];
                foreach ($rows as $row) {
                    $name = is_string($row->name ?? null) ? trim($row->name) : '';
                    if ($name !== '' && preg_match('/^\[[a-z0-9]\]$/i', $name)) {
                        $idsToDelete[] = $row->id;
                    }
                }
                if (! empty($idsToDelete)) {
                    DB::table('wikipedia_game_companies')->whereIn('id', $idsToDelete)->delete();
                }
            });
    }

    public function down(): void
    {
        // Non-reversible: deleted junk records cannot be restored.
    }
};

