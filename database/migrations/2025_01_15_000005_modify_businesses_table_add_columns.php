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
        // Check if businesses table exists
        if (!Schema::hasTable('businesses')) {
            return;
        }

        Schema::table('businesses', function (Blueprint $table) {
            // Kolom tambahan yang diperlukan
            $table->json('types')->nullable()->after('category'); // menyimpan semua types dari Google Places
            $table->string('phone')->nullable()->after('address');
            $table->string('website')->nullable()->after('phone');
            $table->json('opening_hours')->nullable()->after('website');
            $table->integer('price_level')->nullable()->after('opening_hours'); // level harga 1-5
            $table->json('photo_metadata')->nullable()->after('price_level'); // metadata foto (jumlah, uploader, tanggal)
            $table->json('review_metadata')->nullable()->after('photo_metadata'); // metadata review (first_review_date, review_burst_30d, dll)
            $table->integer('scraped_count')->default(0)->after('review_metadata'); // berapa kali di-scrape
            $table->enum('last_update_type', ['initial', 'weekly', 'manual'])->default('initial')->after('scraped_count');
            
            // Index untuk performance
            $table->index('scraped_count');
            $table->index('last_update_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('businesses', function (Blueprint $table) {
            $table->dropColumn([
                'types',
                'phone', 
                'website',
                'opening_hours',
                'price_level',
                'photo_metadata',
                'review_metadata',
                'scraped_count',
                'last_update_type'
            ]);
        });
    }
};
