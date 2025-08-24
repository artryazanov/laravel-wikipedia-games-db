<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Only applicable for MySQL/MariaDB. SQLite doesn't enforce varchar length and doesn't support MODIFY COLUMN.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql'])) {
            // Increase column length to accommodate long platform names while preserving the unique index.
            DB::statement('ALTER TABLE `wikipedia_game_platforms` MODIFY COLUMN `name` VARCHAR(512) NOT NULL');
        }
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql'])) {
            DB::statement('ALTER TABLE `wikipedia_game_platforms` MODIFY COLUMN `name` VARCHAR(255) NOT NULL');
        }
    }
};
