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
        Schema::table('businesses', function (Blueprint $table) {
            // Index untuk pencarian berdasarkan nama (untuk duplicate detection)
            $table->index('name');
            
            // Index untuk pencarian berdasarkan area
            $table->index('area');
            
            // Index untuk pencarian berdasarkan kategori
            $table->index('category');
            
            // Index untuk pencarian berdasarkan first_seen (untuk filtering)
            $table->index('first_seen');
            
            // Index untuk pencarian berdasarkan review_count (untuk filtering)
            $table->index('review_count');
            
            // Index untuk pencarian berdasarkan rating (untuk filtering)
            $table->index('rating');
            
            // Index composite untuk pencarian berdasarkan area dan kategori
            $table->index(['area', 'category']);
            
            // Index composite untuk pencarian berdasarkan area dan first_seen
            $table->index(['area', 'first_seen']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['area']);
            $table->dropIndex(['category']);
            $table->dropIndex(['first_seen']);
            $table->dropIndex(['review_count']);
            $table->dropIndex(['rating']);
            $table->dropIndex(['area', 'category']);
            $table->dropIndex(['area', 'first_seen']);
        });
    }
};