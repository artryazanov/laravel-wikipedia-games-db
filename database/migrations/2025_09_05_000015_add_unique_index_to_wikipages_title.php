<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wikipedia_game_wikipages')) {
            return;
        }

        Schema::table('wikipedia_game_wikipages', function (Blueprint $table) {
            // Add unique index on title
            $table->unique('title');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('wikipedia_game_wikipages')) {
            return;
        }

        Schema::table('wikipedia_game_wikipages', function (Blueprint $table) {
            // Drop unique index on title
            $table->dropUnique(['title']);
        });
    }
};
