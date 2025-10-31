<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: Must drop indexes before renaming column
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropIndex('businesses_first_seen_index');
                $table->dropIndex('businesses_area_first_seen_index');
            });
            
            DB::statement('ALTER TABLE businesses RENAME COLUMN first_seen TO first_seen_old');
            DB::statement('ALTER TABLE businesses ADD COLUMN first_seen DATETIME NULL');
            DB::statement('UPDATE businesses SET first_seen = first_seen_old');
            DB::statement('ALTER TABLE businesses DROP COLUMN first_seen_old');
            
            // Recreate indexes
            Schema::table('businesses', function (Blueprint $table) {
                $table->index('first_seen');
                $table->index(['area', 'first_seen']);
            });
        } else {
            // MySQL/MariaDB: MODIFY preserves indexes
            DB::statement('ALTER TABLE businesses MODIFY COLUMN first_seen DATETIME');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('businesses', function (Blueprint $table) {
                $table->dropIndex('businesses_first_seen_index');
                $table->dropIndex('businesses_area_first_seen_index');
            });
            
            DB::statement('ALTER TABLE businesses RENAME COLUMN first_seen TO first_seen_old');
            DB::statement('ALTER TABLE businesses ADD COLUMN first_seen DATE NULL');
            DB::statement('UPDATE businesses SET first_seen = first_seen_old');
            DB::statement('ALTER TABLE businesses DROP COLUMN first_seen_old');
            
            Schema::table('businesses', function (Blueprint $table) {
                $table->index('first_seen');
                $table->index(['area', 'first_seen']);
            });
        } else {
            DB::statement('ALTER TABLE businesses MODIFY COLUMN first_seen DATE');
        }
    }
};
