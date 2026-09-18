<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_seller_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('unit');
            $table->decimal('price_per_unit', 10, 2);
            $table->decimal('quantity_available', 10, 2);
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'category_id']);
            $table->index('name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
