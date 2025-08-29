<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Create central wikipages table
        if (! Schema::hasTable('wikipedia_game_wikipages')) {
            Schema::create('wikipedia_game_wikipages', function (Blueprint $table) {
                $table->id();
                $table->string('title')->nullable()->comment('Original Wikipedia page title');
                $table->string('wikipedia_url')->nullable()->unique()->comment('Full URL to the Wikipedia page');
                $table->text('description')->nullable()->comment('Lead/summary text');
                $table->longText('wikitext')->nullable()->comment('Full page content in wikitext');
                $table->timestamps();
                $table->comment('Central storage for Wikipedia page meta reused by multiple entities');
            });
        }

        // 2) Add wikipage_id to all entities that previously stored page fields
        $targets = [
            'wikipedia_games',
            'wikipedia_game_companies',
            'wikipedia_game_engines',
            'wikipedia_game_genres',
            'wikipedia_game_modes',
            'wikipedia_game_platforms',
            'wikipedia_game_series',
        ];

        foreach ($targets as $table) {
            if (Schema::hasTable($table)) {
                Schema::table($table, function (Blueprint $table) {
                    if (! Schema::hasColumn($table->getTable(), 'wikipage_id')) {
                        $table->foreignId('wikipage_id')->nullable()->after('id')->constrained('wikipedia_game_wikipages')->nullOnDelete();
                    }
                });
            }
        }

        // 3) Migrate data into central table and backfill wikipage_id
        $this->migrateTable('wikipedia_games', 'title');
        $this->migrateTable('wikipedia_game_companies');
        $this->migrateTable('wikipedia_game_engines');
        $this->migrateTable('wikipedia_game_genres');
        $this->migrateTable('wikipedia_game_modes');
        $this->migrateTable('wikipedia_game_platforms');
        $this->migrateTable('wikipedia_game_series');

        // 4) Drop moved columns from the original tables
        $dropCols = ['title', 'wikipedia_url', 'description', 'wikitext'];
        foreach ($targets as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            // Special handling for wikipedia_games: drop unique indexes before dropping columns
            if ($table === 'wikipedia_games') {
                $driver = DB::getDriverName();
                try {
                    if ($driver === 'sqlite') {
                        DB::statement('DROP INDEX IF EXISTS wikipedia_games_title_unique');
                        DB::statement('DROP INDEX IF EXISTS wikipedia_games_wikipedia_url_unique');
                    } else {
                        Schema::table($table, function (Blueprint $table) {
                            try { $table->dropUnique('wikipedia_games_title_unique'); } catch (Throwable $e) {}
                            try { $table->dropUnique('wikipedia_games_wikipedia_url_unique'); } catch (Throwable $e) {}
                        });
                    }
                } catch (Throwable $e) {
                    // Ignore if indexes do not exist
                }
            }

            Schema::table($table, function (Blueprint $table) use ($dropCols) {
                foreach ($dropCols as $col) {
                    if (Schema::hasColumn($table->getTable(), $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }

    /**
     * Copy page fields into wikipedia_game_wikipages and set wikipage_id on source rows.
     */
    private function migrateTable(string $table, string $titleColumnFallback = 'name'): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        // Determine which columns exist in this source table
        $hasTitle = Schema::hasColumn($table, 'title');
        $hasUrl = Schema::hasColumn($table, 'wikipedia_url');
        $hasDesc = Schema::hasColumn($table, 'description');
        $hasWikitext = Schema::hasColumn($table, 'wikitext');

        // If none of the columns exist, nothing to migrate
        if (! $hasTitle && ! $hasUrl && ! $hasDesc && ! $hasWikitext) {
            return;
        }

        // Process in chunks to avoid memory issues
        DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table, $hasTitle, $hasUrl, $hasDesc, $hasWikitext, $titleColumnFallback) {
            foreach ($rows as $row) {
                $title = $hasTitle ? ($row->title ?? null) : ($row->{$titleColumnFallback} ?? null);
                $url = $hasUrl ? ($row->wikipedia_url ?? null) : null;
                $desc = $hasDesc ? ($row->description ?? null) : null;
                $wikitext = $hasWikitext ? ($row->wikitext ?? null) : null;

                // Skip if both title and url are empty and there's nothing to store
                if ($title === null && $url === null && $desc === null && $wikitext === null) {
                    continue;
                }

                // Try to reuse existing wikipage by url first, then by exact title match
                $wikipageId = DB::table('wikipedia_game_wikipages')
                    ->when($url, fn ($q) => $q->orWhere('wikipedia_url', $url))
                    ->when($title, fn ($q) => $q->orWhere('title', $title))
                    ->value('id');

                if (! $wikipageId) {
                    $wikipageId = DB::table('wikipedia_game_wikipages')->insertGetId([
                        'title' => $title,
                        'wikipedia_url' => $url,
                        'description' => $desc,
                        'wikitext' => $wikitext,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Update existing with first non-null values on PHP side
                    $existing = DB::table('wikipedia_game_wikipages')->where('id', $wikipageId)->first();
                    $merged = [
                        'title' => $existing->title ?? $title,
                        'wikipedia_url' => $existing->wikipedia_url ?? $url,
                        'description' => $existing->description ?? $desc,
                        'wikitext' => $existing->wikitext ?? $wikitext,
                        'updated_at' => now(),
                    ];
                    DB::table('wikipedia_game_wikipages')->where('id', $wikipageId)->update($merged);
                }

                // Backfill reference on source row if empty
                if (is_null($row->wikipage_id ?? null)) {
                    DB::table($table)->where('id', $row->id)->update(['wikipage_id' => $wikipageId]);
                }
            }
        });
    }

    public function down(): void
    {
        // Attempt best-effort rollback: re-add columns, copy back minimal data, then drop FK and table
        $targets = [
            'wikipedia_games' => 'title',
            'wikipedia_game_companies' => 'name',
            'wikipedia_game_engines' => 'name',
            'wikipedia_game_genres' => 'name',
            'wikipedia_game_modes' => 'name',
            'wikipedia_game_platforms' => 'name',
            'wikipedia_game_series' => 'name',
        ];

        foreach ($targets as $table => $fallback) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            Schema::table($table, function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext'] as $col) {
                    if (! Schema::hasColumn($table->getTable(), $col)) {
                        // Use defaults consistent with prior migrations
                        if (in_array($table->getTable(), ['wikipedia_games'], true) && in_array($col, ['title', 'wikipedia_url'], true)) {
                            $table->string($col)->nullable();
                        } elseif (in_array($col, ['description'])) {
                            $table->text($col)->nullable();
                        } elseif (in_array($col, ['wikitext'])) {
                            $table->longText($col)->nullable();
                        } else {
                            $table->string($col)->nullable();
                        }
                    }
                }
            });

            // Copy data back from wikipages when available
            DB::table($table)->orderBy('id')->chunkById(500, function ($rows) use ($table) {
                foreach ($rows as $row) {
                    if (! isset($row->wikipage_id) || ! $row->wikipage_id) {
                        continue;
                    }
                    $wp = DB::table('wikipedia_game_wikipages')->where('id', $row->wikipage_id)->first();
                    if (! $wp) {
                        continue;
                    }
                    DB::table($table)->where('id', $row->id)->update([
                        'title' => $wp->title,
                        'wikipedia_url' => $wp->wikipedia_url,
                        'description' => $wp->description,
                        'wikitext' => $wp->wikitext,
                    ]);
                }
            });

            // Drop FK column
            Schema::table($table, function (Blueprint $table) {
                if (Schema::hasColumn($table->getTable(), 'wikipage_id')) {
                    $table->dropConstrainedForeignId('wikipage_id');
                }
            });
        }

        // Finally, drop central table
        Schema::dropIfExists('wikipedia_game_wikipages');
    }
};
