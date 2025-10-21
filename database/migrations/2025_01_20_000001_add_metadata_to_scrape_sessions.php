<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('scrape_sessions', function (Blueprint $table) {
            // Add metadata column if not exists
            if (!Schema::hasColumn('scrape_sessions', 'metadata')) {
                $table->json('metadata')->nullable()->after('error_log')
                    ->comment('Stores optimization metrics, rejection counts, and feature usage tracking');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scrape_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('scrape_sessions', 'metadata')) {
                $table->dropColumn('metadata');
            }
        });
    }
};

