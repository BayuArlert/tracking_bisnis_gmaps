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
        Schema::create('businesses', function (Blueprint $table) {
            $table->id();
            $table->string('place_id')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('address')->nullable();
            $table->string('area')->nullable(); 
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->float('rating')->nullable();
            $table->integer('review_count')->default(0);
            $table->date('first_seen');
            $table->dateTime('last_fetched')->nullable();
            $table->json('indicators')->nullable(); // simpan tanda deteksi
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('businesses');
    }
};
