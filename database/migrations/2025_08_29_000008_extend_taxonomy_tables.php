<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration that extends taxonomy tables with extra metadata columns.
 * It safely adds columns only if they do not already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Companies
        if (Schema::hasTable('wikipedia_game_companies')) {
            Schema::table('wikipedia_game_companies', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_companies', 'title')) {
                    $table->string('title')->nullable()->after('slug')->comment('Original Wikipedia page title.');
                }
                if (! Schema::hasColumn('wikipedia_game_companies', 'wikipedia_url')) {
                    $table->string('wikipedia_url')->nullable()->after('title')->comment('URL to the company page on Wikipedia.');
                }
                if (! Schema::hasColumn('wikipedia_game_companies', 'description')) {
                    $table->text('description')->nullable()->after('wikipedia_url')->comment('Lead summary/description from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_companies', 'wikitext')) {
                    $table->longText('wikitext')->nullable()->after('description')->comment('Full page wikitext content.');
                }
                if (! Schema::hasColumn('wikipedia_game_companies', 'cover_image_url')) {
                    $table->string('cover_image_url')->nullable()->after('wikitext')->comment('Main image URL extracted from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_companies', 'founded')) {
                    $table->unsignedSmallInteger('founded')->nullable()->after('cover_image_url')->comment('Year the company was founded.');
                }
                if (! Schema::hasColumn('wikipedia_game_companies', 'website_url')) {
                    $table->string('website_url')->nullable()->after('founded')->comment('Official website URL.');
                }
            });
        }

        // Platforms
        if (Schema::hasTable('wikipedia_game_platforms')) {
            Schema::table('wikipedia_game_platforms', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_platforms', 'title')) {
                    $table->string('title')->nullable()->after('slug')->comment('Original Wikipedia page title.');
                }
                if (! Schema::hasColumn('wikipedia_game_platforms', 'wikipedia_url')) {
                    $table->string('wikipedia_url')->nullable()->after('title')->comment('URL to the platform page on Wikipedia.');
                }
                if (! Schema::hasColumn('wikipedia_game_platforms', 'description')) {
                    $table->text('description')->nullable()->after('wikipedia_url')->comment('Lead summary/description from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_platforms', 'wikitext')) {
                    $table->longText('wikitext')->nullable()->after('description')->comment('Full page wikitext content.');
                }
                if (! Schema::hasColumn('wikipedia_game_platforms', 'cover_image_url')) {
                    $table->string('cover_image_url')->nullable()->after('wikitext')->comment('Main image URL extracted from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_platforms', 'release_date')) {
                    $table->date('release_date')->nullable()->after('cover_image_url')->comment('Platform release/launch date.');
                }
                if (! Schema::hasColumn('wikipedia_game_platforms', 'website_url')) {
                    $table->string('website_url')->nullable()->after('release_date')->comment('Official website URL.');
                }
            });
        }

        // Engines
        if (Schema::hasTable('wikipedia_game_engines')) {
            Schema::table('wikipedia_game_engines', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_engines', 'title')) {
                    $table->string('title')->nullable()->after('slug')->comment('Original Wikipedia page title.');
                }
                if (! Schema::hasColumn('wikipedia_game_engines', 'wikipedia_url')) {
                    $table->string('wikipedia_url')->nullable()->after('title')->comment('URL to the engine page on Wikipedia.');
                }
                if (! Schema::hasColumn('wikipedia_game_engines', 'description')) {
                    $table->text('description')->nullable()->after('wikipedia_url')->comment('Lead summary/description from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_engines', 'wikitext')) {
                    $table->longText('wikitext')->nullable()->after('description')->comment('Full page wikitext content.');
                }
                if (! Schema::hasColumn('wikipedia_game_engines', 'cover_image_url')) {
                    $table->string('cover_image_url')->nullable()->after('wikitext')->comment('Main image URL extracted from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_engines', 'release_date')) {
                    $table->date('release_date')->nullable()->after('cover_image_url')->comment('Engine initial release date.');
                }
                if (! Schema::hasColumn('wikipedia_game_engines', 'website_url')) {
                    $table->string('website_url')->nullable()->after('release_date')->comment('Official website URL.');
                }
            });
        }

        // Genres
        if (Schema::hasTable('wikipedia_game_genres')) {
            Schema::table('wikipedia_game_genres', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_genres', 'title')) {
                    $table->string('title')->nullable()->after('slug')->comment('Original Wikipedia page title.');
                }
                if (! Schema::hasColumn('wikipedia_game_genres', 'wikipedia_url')) {
                    $table->string('wikipedia_url')->nullable()->after('title')->comment('URL to the genre page on Wikipedia.');
                }
                if (! Schema::hasColumn('wikipedia_game_genres', 'description')) {
                    $table->text('description')->nullable()->after('wikipedia_url')->comment('Lead summary/description from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_genres', 'wikitext')) {
                    $table->longText('wikitext')->nullable()->after('description')->comment('Full page wikitext content.');
                }
            });
        }

        // Modes
        if (Schema::hasTable('wikipedia_game_modes')) {
            Schema::table('wikipedia_game_modes', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_modes', 'title')) {
                    $table->string('title')->nullable()->after('slug')->comment('Original Wikipedia page title.');
                }
                if (! Schema::hasColumn('wikipedia_game_modes', 'wikipedia_url')) {
                    $table->string('wikipedia_url')->nullable()->after('title')->comment('URL to the mode page on Wikipedia.');
                }
                if (! Schema::hasColumn('wikipedia_game_modes', 'description')) {
                    $table->text('description')->nullable()->after('wikipedia_url')->comment('Lead summary/description from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_modes', 'wikitext')) {
                    $table->longText('wikitext')->nullable()->after('description')->comment('Full page wikitext content.');
                }
            });
        }

        // Series
        if (Schema::hasTable('wikipedia_game_series')) {
            Schema::table('wikipedia_game_series', function (Blueprint $table) {
                if (! Schema::hasColumn('wikipedia_game_series', 'title')) {
                    $table->string('title')->nullable()->after('slug')->comment('Original Wikipedia page title.');
                }
                if (! Schema::hasColumn('wikipedia_game_series', 'wikipedia_url')) {
                    $table->string('wikipedia_url')->nullable()->after('title')->comment('URL to the series page on Wikipedia.');
                }
                if (! Schema::hasColumn('wikipedia_game_series', 'description')) {
                    $table->text('description')->nullable()->after('wikipedia_url')->comment('Lead summary/description from the page.');
                }
                if (! Schema::hasColumn('wikipedia_game_series', 'wikitext')) {
                    $table->longText('wikitext')->nullable()->after('description')->comment('Full page wikitext content.');
                }
            });
        }
    }

    public function down(): void
    {
        // Companies
        if (Schema::hasTable('wikipedia_game_companies')) {
            Schema::table('wikipedia_game_companies', function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext', 'cover_image_url', 'founded', 'website_url'] as $col) {
                    if (Schema::hasColumn('wikipedia_game_companies', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Platforms
        if (Schema::hasTable('wikipedia_game_platforms')) {
            Schema::table('wikipedia_game_platforms', function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext', 'cover_image_url', 'release_date', 'website_url'] as $col) {
                    if (Schema::hasColumn('wikipedia_game_platforms', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Engines
        if (Schema::hasTable('wikipedia_game_engines')) {
            Schema::table('wikipedia_game_engines', function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext', 'cover_image_url', 'release_date', 'website_url'] as $col) {
                    if (Schema::hasColumn('wikipedia_game_engines', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Genres
        if (Schema::hasTable('wikipedia_game_genres')) {
            Schema::table('wikipedia_game_genres', function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext'] as $col) {
                    if (Schema::hasColumn('wikipedia_game_genres', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Modes
        if (Schema::hasTable('wikipedia_game_modes')) {
            Schema::table('wikipedia_game_modes', function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext'] as $col) {
                    if (Schema::hasColumn('wikipedia_game_modes', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }

        // Series
        if (Schema::hasTable('wikipedia_game_series')) {
            Schema::table('wikipedia_game_series', function (Blueprint $table) {
                foreach (['title', 'wikipedia_url', 'description', 'wikitext'] as $col) {
                    if (Schema::hasColumn('wikipedia_game_series', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
