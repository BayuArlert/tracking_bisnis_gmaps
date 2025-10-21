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
        Schema::create('bali_regions', function (Blueprint $table) {
            $table->id();
            $table->enum('type', ['kabupaten', 'kecamatan', 'desa']);
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('bali_regions')->onDelete('cascade');
            $table->decimal('center_lat', 10, 7)->nullable();
            $table->decimal('center_lng', 10, 7)->nullable();
            $table->integer('search_radius')->default(5000); // dalam meter
            $table->integer('priority')->default(1); // untuk optimize scraping order
            $table->timestamps();
            
            // Index untuk performance
            $table->index(['type', 'parent_id']);
            $table->index('priority');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bali_regions');
    }
};
