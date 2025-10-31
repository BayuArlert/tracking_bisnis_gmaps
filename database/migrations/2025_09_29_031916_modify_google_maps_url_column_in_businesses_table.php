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
        // Avoid using change() to prevent doctrine/dbal requirement in production
        DB::statement('ALTER TABLE businesses MODIFY COLUMN google_maps_url TEXT NULL');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE businesses MODIFY COLUMN google_maps_url VARCHAR(255) NULL');
    }
};
