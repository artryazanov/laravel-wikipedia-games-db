<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wikipedia_games') || ! Schema::hasTable('wikipedia_game_game_company')) {
            return; // Nothing to do if core tables are missing
        }

        // 1) Find games that are missing either developers OR publishers (or both)
        $gameIdsToDelete = [];
        $wikipageIdsCandidate = [];

        DB::table('wikipedia_games as g')
            ->leftJoin('wikipedia_game_game_company as gdev', function ($join) {
                $join->on('gdev.game_id', '=', 'g.id')
                     ->where('gdev.role', '=', 'developer');
            })
            ->leftJoin('wikipedia_game_game_company as gpub', function ($join) {
                $join->on('gpub.game_id', '=', 'g.id')
                     ->where('gpub.role', '=', 'publisher');
            })
            ->where(function ($q) {
                $q->whereNull('gdev.game_id')
                  ->orWhereNull('gpub.game_id');
            })
            ->orderBy('g.id')
            ->select('g.id as id', 'g.wikipage_id as wikipage_id')
            ->chunkById(500, function ($rows) use (&$gameIdsToDelete, &$wikipageIdsCandidate) {
                foreach ($rows as $row) {
                    $gameIdsToDelete[] = $row->id;
                    if (! is_null($row->wikipage_id)) {
                        $wikipageIdsCandidate[] = (int) $row->wikipage_id;
                    }
                }
            });

        if ($gameIdsToDelete === []) {
            return; // Nothing to delete
        }

        // 2) Delete the identified games (cascade will clean pivot rows)
        DB::table('wikipedia_games')->whereIn('id', $gameIdsToDelete)->delete();

        // 3) Delete orphaned wikipages that were linked to the deleted games but are no longer referenced
        if (Schema::hasTable('wikipedia_game_wikipages') && $wikipageIdsCandidate !== []) {
            $wikipageIdsCandidate = array_values(array_unique(array_map('intval', $wikipageIdsCandidate)));

            // Determine which candidate wikipages are still referenced by any entity
            $referenceTables = [
                'wikipedia_games',
                'wikipedia_game_companies',
                'wikipedia_game_engines',
                'wikipedia_game_genres',
                'wikipedia_game_modes',
                'wikipedia_game_platforms',
                'wikipedia_game_series',
            ];

            $stillReferenced = [];
            foreach ($referenceTables as $table) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'wikipage_id')) {
                    continue;
                }
                $ids = DB::table($table)
                    ->whereIn('wikipage_id', $wikipageIdsCandidate)
                    ->distinct()
                    ->pluck('wikipage_id')
                    ->all();
                foreach ($ids as $id) {
                    if ($id !== null) {
                        $stillReferenced[(int) $id] = true;
                    }
                }
            }

            $toDelete = array_values(array_filter($wikipageIdsCandidate, function ($id) use ($stillReferenced) {
                return ! isset($stillReferenced[(int) $id]);
            }));

            if ($toDelete !== []) {
                DB::table('wikipedia_game_wikipages')->whereIn('id', $toDelete)->delete();
            }
        }
    }

    public function down(): void
    {
        // Non-reversible cleanup.
    }
};

