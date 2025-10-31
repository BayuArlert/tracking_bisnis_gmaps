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
        // SQLite doesn't support MODIFY COLUMN, use fallback approach
        if (DB::getDriverName() === 'sqlite') {
            // SQLite: Rename -> Add new -> Copy -> Drop old
            DB::statement('ALTER TABLE businesses RENAME COLUMN google_maps_url TO google_maps_url_old');
            DB::statement('ALTER TABLE businesses ADD COLUMN google_maps_url TEXT NULL');
            DB::statement('UPDATE businesses SET google_maps_url = google_maps_url_old');
            DB::statement('ALTER TABLE businesses DROP COLUMN google_maps_url_old');
        } else {
            // MySQL/MariaDB: Use MODIFY
            DB::statement('ALTER TABLE businesses MODIFY COLUMN google_maps_url TEXT NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE businesses RENAME COLUMN google_maps_url TO google_maps_url_old');
            DB::statement('ALTER TABLE businesses ADD COLUMN google_maps_url VARCHAR(255) NULL');
            DB::statement('UPDATE businesses SET google_maps_url = google_maps_url_old');
            DB::statement('ALTER TABLE businesses DROP COLUMN google_maps_url_old');
        } else {
            DB::statement('ALTER TABLE businesses MODIFY COLUMN google_maps_url VARCHAR(255) NULL');
        }
    }
};
