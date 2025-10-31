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
            DB::statement('ALTER TABLE businesses RENAME COLUMN first_seen TO first_seen_old');
            DB::statement('ALTER TABLE businesses ADD COLUMN first_seen DATETIME NULL');
            DB::statement('UPDATE businesses SET first_seen = first_seen_old');
            DB::statement('ALTER TABLE businesses DROP COLUMN first_seen_old');
        } else {
            DB::statement('ALTER TABLE businesses MODIFY COLUMN first_seen DATETIME');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE businesses RENAME COLUMN first_seen TO first_seen_old');
            DB::statement('ALTER TABLE businesses ADD COLUMN first_seen DATE NULL');
            DB::statement('UPDATE businesses SET first_seen = first_seen_old');
            DB::statement('ALTER TABLE businesses DROP COLUMN first_seen_old');
        } else {
            DB::statement('ALTER TABLE businesses MODIFY COLUMN first_seen DATE');
        }
    }
};
