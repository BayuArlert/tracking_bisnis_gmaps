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
        Schema::create('category_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('brief_category'); // e.g., 'Café'
            $table->json('google_types'); // ['cafe', 'coffee_shop']
            $table->json('keywords_id')->nullable(); // ['warung kopi', 'kedai kopi', 'coffee roastery']
            $table->json('keywords_en')->nullable(); // ['coffee shop', 'café', 'espresso bar']
            $table->json('text_search_queries')->nullable(); // pre-defined queries
            $table->timestamps();
            
            // Index untuk performance
            $table->index('brief_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_mappings');
    }
};
