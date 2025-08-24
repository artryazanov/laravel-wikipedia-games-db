<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated migration for Wikipedia Games DB schema.
 * Creates all package tables with proper comments and foreign keys.
 * Safe to add to existing projects: each table is created only if it does not exist.
 */
return new class extends Migration
{
    /**
     * Run the migrations: create all tables in dependency order.
     */
    public function up(): void
    {
        // Core games table
        if (! Schema::hasTable('wikipedia_games')) {
            Schema::create('wikipedia_games', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the game.');
                $table->string('title')->unique()->comment('Unique game title as appears on Wikipedia.');
                $table->string('clean_title')->nullable()->index()->comment('Normalized game title without disambiguation (for searching).');
                $table->string('wikipedia_url')->unique()->comment('Unique URL to the game page on Wikipedia.');
                $table->text('description')->nullable()->comment('Short description or lead section excerpt.');
                $table->longText('wikitext')->nullable()->comment('Full page content in wiki markup (wikitext).');
                $table->string('cover_image_url')->nullable()->comment('URL of the cover image extracted from the infobox.');
                $table->date('release_date')->nullable()->comment('First known release date.');
                $table->unsignedSmallInteger('release_year')->nullable()->comment('First 4-digit release year parsed from the infobox.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores video games scraped from Wikipedia.');
            });
        }

        // Taxonomy tables
        if (! Schema::hasTable('wikipedia_game_genres')) {
            Schema::create('wikipedia_game_genres', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the genre.');
                $table->string('name')->unique()->comment('Unique human-readable name of the genre (e.g., RPG).');
                $table->string('slug')->unique()->comment('Unique URL-friendly slug for the genre.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores game genres for many-to-many relation with games.');
            });
        }

        if (! Schema::hasTable('wikipedia_game_platforms')) {
            Schema::create('wikipedia_game_platforms', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the platform.');
                $table->string('name')->unique()->comment('Unique human-readable platform name (e.g., Windows, PlayStation 5).');
                $table->string('slug')->unique()->comment('Unique URL-friendly slug for the platform.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores platforms for many-to-many relation with games.');
            });
        }

        if (! Schema::hasTable('wikipedia_game_companies')) {
            Schema::create('wikipedia_game_companies', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the company.');
                $table->string('name')->unique()->comment('Unique human-readable company name (e.g., Ubisoft).');
                $table->string('slug')->unique()->comment('Unique URL-friendly slug for the company.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores companies which can be developers or publishers of games.');
            });
        }

        // Modes taxonomy
        if (! Schema::hasTable('wikipedia_game_modes')) {
            Schema::create('wikipedia_game_modes', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the game mode.');
                $table->string('name')->unique()->comment('Unique human-readable mode name (e.g., Single-player, Multiplayer).');
                $table->string('slug')->unique()->comment('Unique URL-friendly slug for the mode.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores game modes for many-to-many relation with games.');
            });
        }

        // Series taxonomy
        if (! Schema::hasTable('wikipedia_game_series')) {
            Schema::create('wikipedia_game_series', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the game series.');
                $table->string('name')->unique()->comment('Unique human-readable series name (e.g., The Legend of Zelda).');
                $table->string('slug')->unique()->comment('Unique URL-friendly slug for the series.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores game series/franchises for many-to-many relation with games.');
            });
        }

        // Engines taxonomy
        if (! Schema::hasTable('wikipedia_game_engines')) {
            Schema::create('wikipedia_game_engines', function (Blueprint $table) {
                $table->id()->comment('Primary key. Unique identifier of the game engine.');
                $table->string('name')->unique()->comment('Unique human-readable engine name (e.g., Unreal Engine 5).');
                $table->string('slug')->unique()->comment('Unique URL-friendly slug for the engine.');
                $table->timestamp('created_at')->nullable()->useCurrent()->comment('Timestamp when the record was created.');
                $table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->comment('Timestamp when the record was last updated.');
                $table->comment('Stores game engines for many-to-many relation with games.');
            });
        }

        // Pivot tables
        if (! Schema::hasTable('wikipedia_game_game_genre')) {
            Schema::create('wikipedia_game_game_genre', function (Blueprint $table) {
                $table->foreignId('game_id')->comment('Foreign key referencing wikipedia_games.id')->constrained('wikipedia_games')->onDelete('cascade');
                $table->foreignId('genre_id')->comment('Foreign key referencing wikipedia_game_genres.id')->constrained('wikipedia_game_genres')->onDelete('cascade');
                $table->primary(['game_id', 'genre_id']);
                $table->comment('Pivot table linking games and genres (many-to-many).');
            });
        }

        if (! Schema::hasTable('wikipedia_game_game_platform')) {
            Schema::create('wikipedia_game_game_platform', function (Blueprint $table) {
                $table->foreignId('game_id')->comment('Foreign key referencing wikipedia_games.id')->constrained('wikipedia_games')->onDelete('cascade');
                $table->foreignId('platform_id')->comment('Foreign key referencing wikipedia_game_platforms.id')->constrained('wikipedia_game_platforms')->onDelete('cascade');
                $table->primary(['game_id', 'platform_id']);
                $table->comment('Pivot table linking games and platforms (many-to-many).');
            });
        }

        if (! Schema::hasTable('wikipedia_game_game_mode')) {
            Schema::create('wikipedia_game_game_mode', function (Blueprint $table) {
                $table->foreignId('game_id')->comment('Foreign key referencing wikipedia_games.id')->constrained('wikipedia_games')->onDelete('cascade');
                $table->foreignId('mode_id')->comment('Foreign key referencing wikipedia_game_modes.id')->constrained('wikipedia_game_modes')->onDelete('cascade');
                $table->primary(['game_id', 'mode_id']);
                $table->comment('Pivot table linking games and modes (many-to-many).');
            });
        }

        if (! Schema::hasTable('wikipedia_game_game_series')) {
            Schema::create('wikipedia_game_game_series', function (Blueprint $table) {
                $table->foreignId('game_id')->comment('Foreign key referencing wikipedia_games.id')->constrained('wikipedia_games')->onDelete('cascade');
                $table->foreignId('series_id')->comment('Foreign key referencing wikipedia_game_series.id')->constrained('wikipedia_game_series')->onDelete('cascade');
                $table->primary(['game_id', 'series_id']);
                $table->comment('Pivot table linking games and series (many-to-many).');
            });
        }

        if (! Schema::hasTable('wikipedia_game_game_engine')) {
            Schema::create('wikipedia_game_game_engine', function (Blueprint $table) {
                $table->foreignId('game_id')->comment('Foreign key referencing wikipedia_games.id')->constrained('wikipedia_games')->onDelete('cascade');
                $table->foreignId('engine_id')->comment('Foreign key referencing wikipedia_game_engines.id')->constrained('wikipedia_game_engines')->onDelete('cascade');
                $table->primary(['game_id', 'engine_id']);
                $table->comment('Pivot table linking games and engines (many-to-many).');
            });
        }

        if (! Schema::hasTable('wikipedia_game_game_company')) {
            Schema::create('wikipedia_game_game_company', function (Blueprint $table) {
                $table->foreignId('game_id')->comment('Foreign key referencing wikipedia_games.id')->constrained('wikipedia_games')->onDelete('cascade');
                $table->foreignId('company_id')->comment('Foreign key referencing wikipedia_game_companies.id')->constrained('wikipedia_game_companies')->onDelete('cascade');
                $table->enum('role', ['developer', 'publisher'])->comment('Role of the company in relation to the game.');
                $table->primary(['game_id', 'company_id', 'role']);
                $table->comment('Pivot table linking games and companies with role metadata.');
            });
        }
    }

    /**
     * Reverse the migrations: drop tables in reverse dependency order.
     */
    public function down(): void
    {
        Schema::dropIfExists('wikipedia_game_game_company');
        Schema::dropIfExists('wikipedia_game_game_engine');
        Schema::dropIfExists('wikipedia_game_game_series');
        Schema::dropIfExists('wikipedia_game_game_mode');
        Schema::dropIfExists('wikipedia_game_game_platform');
        Schema::dropIfExists('wikipedia_game_game_genre');
        Schema::dropIfExists('wikipedia_game_engines');
        Schema::dropIfExists('wikipedia_game_series');
        Schema::dropIfExists('wikipedia_game_modes');
        Schema::dropIfExists('wikipedia_game_companies');
        Schema::dropIfExists('wikipedia_game_platforms');
        Schema::dropIfExists('wikipedia_game_genres');
        Schema::dropIfExists('wikipedia_games');
    }
};
