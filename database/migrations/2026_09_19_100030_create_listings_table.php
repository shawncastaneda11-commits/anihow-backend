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

            // Denormalized from the seller so farm-scoped queries do not join users.
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();

            $table->foreignId('crop_type_id')->constrained()->restrictOnDelete();

            // The seller's own copy, not the crop identity. Crop identity is
            // crop_type_id. Unit of measure lives on the crop type.
            $table->string('title');
            $table->text('description')->nullable();

            $table->decimal('price_per_unit', 10, 2);
            $table->decimal('quantity_available', 10, 2);

            // Held by Placed orders, deducted from quantity_available at
            // Confirmed. Sellable = quantity_available - quantity_held.
            $table->decimal('quantity_held', 10, 2)->default(0);

            // Cover image. Additional images live in listing_photos.
            $table->string('image_path')->nullable();

            // Seller's own availability switch.
            $table->boolean('is_active')->default(true);

            // published on create. Super Admin holds takedown, not approval.
            $table->string('status')->default('published');
            $table->timestamp('taken_down_at')->nullable();
            $table->foreignId('taken_down_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('takedown_reason')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'is_active', 'crop_type_id']);
            $table->index(['farm_id', 'status']);
            $table->index(['farmer_seller_id', 'status']);
            $table->index('title');
        });

        Schema::create('listing_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['listing_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_photos');
        Schema::dropIfExists('listings');
    }
};
