<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('farm_crop_type_overrides', function (Blueprint $table) {
            $table->id();

            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_type_id')->constrained()->cascadeOnDelete();

            // Null means the system value on the crop type applies.
            // A row where both are null is never written; see SetFarmPriceOverrideAction (Pass 2B).
            $table->decimal('floor_price', 10, 2)->nullable();
            $table->decimal('max_discount', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['farm_id', 'crop_type_id']);
        });

        // No CHECK constraint here. The crop_types CHECK (max_discount < floor_price) compares
        // system values only, and tighten-only validation preserves that invariant at farm level:
        // effective ceiling <= system max < system floor <= effective floor.
    }

    public function down(): void
    {
        Schema::dropIfExists('farm_crop_type_overrides');
    }
};
