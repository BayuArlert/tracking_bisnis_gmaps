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
        Schema::create('business_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->onDelete('cascade');
            $table->date('snapshot_date');
            $table->integer('review_count')->default(0);
            $table->float('rating')->nullable();
            $table->integer('photo_count')->default(0);
            $table->json('indicators')->nullable(); // snapshot indicators saat itu
            $table->timestamps();
            
            // Index untuk performance
            $table->index(['business_id', 'snapshot_date']);
            $table->index('snapshot_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_snapshots');
    }
};
