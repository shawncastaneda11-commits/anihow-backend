<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Saving single listings as favorites was never reachable from the app.
 * Buyers keep favorite shops and followed farms, which have their own tables.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('favorites');
    }

    public function down(): void
    {
        Schema::create('favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['buyer_id', 'listing_id']);
        });
    }
};
