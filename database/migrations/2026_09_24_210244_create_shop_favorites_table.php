<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_favorites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('farmer_seller_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['buyer_id', 'farmer_seller_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_favorites');
    }
};
