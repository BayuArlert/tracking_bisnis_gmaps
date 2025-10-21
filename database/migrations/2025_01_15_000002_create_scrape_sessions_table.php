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
        Schema::create('scrape_sessions', function (Blueprint $table) {
            $table->id();
            $table->enum('session_type', ['initial', 'weekly', 'manual']);
            $table->string('target_area'); // e.g., "Kabupaten Badung"
            $table->json('target_categories')->nullable(); // kategori yang di-scrape
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->integer('api_calls_count')->default(0);
            $table->decimal('estimated_cost', 10, 4)->default(0);
            $table->integer('businesses_found')->default(0);
            $table->integer('businesses_new')->default(0);
            $table->integer('businesses_updated')->default(0);
            $table->enum('status', ['running', 'completed', 'failed'])->default('running');
            $table->text('error_log')->nullable();
            $table->timestamps();
            
            // Index untuk performance
            $table->index(['session_type', 'status']);
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scrape_sessions');
    }
};
