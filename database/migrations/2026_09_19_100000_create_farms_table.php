<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('contact_person')->nullable();
            $table->string('contact_number')->nullable();
            $table->string('address')->nullable();
            $table->string('barangay')->nullable();
            $table->string('municipality')->nullable();

            // Free text. No geocoding, no map, no route.
            $table->string('pickup_point')->nullable();

            $table->string('cover_photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('farm_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['farm_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_photos');
        Schema::dropIfExists('farms');
    }
};
