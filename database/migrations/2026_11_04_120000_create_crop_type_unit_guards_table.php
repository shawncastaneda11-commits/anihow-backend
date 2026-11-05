<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Price limits for a crop's other selling units.
 *
 * A crop's own floor_price and max_discount stay the limits for its main
 * unit (unit_of_measure) and every unit that converts to it. A row here adds
 * one more unit family: sitaw guarded per kg can also be sold per bundle once
 * it has a bundle row. Package units (bundle, sack, tray, pack, bottle) never
 * convert, so each needs its own row. Nothing is copied or moved: existing
 * crops, listings and farm overrides keep working as they are.
 *
 * Farm overrides gain a unit. Blank is the main unit, so every existing
 * override keeps applying where it did.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_type_unit_guards', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crop_type_id')->constrained()->cascadeOnDelete();
            // App\Enums\ListingUnit. At most one row per unit family per crop.
            $table->string('unit');
            $table->decimal('floor_price', 12, 4);
            // Blank is no maximum: the floor alone limits a tawad.
            $table->decimal('max_discount', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['crop_type_id', 'unit']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE crop_type_unit_guards ADD CONSTRAINT chk_unit_guards_floor_positive CHECK (floor_price > 0)');
            DB::statement('ALTER TABLE crop_type_unit_guards ADD CONSTRAINT chk_unit_guards_discount_below_floor '
                .'CHECK (max_discount IS NULL OR (max_discount >= 0 AND max_discount < floor_price))');
        }

        Schema::table('farm_crop_type_overrides', function (Blueprint $table): void {
            $table->string('unit')->nullable()->after('crop_type_id');
            // Added before the old key is dropped, so the farm_id foreign key
            // always has an index to use.
            $table->unique(['farm_id', 'crop_type_id', 'unit'], 'farm_overrides_farm_crop_unit_unique');
        });

        Schema::table('farm_crop_type_overrides', function (Blueprint $table): void {
            $table->dropUnique(['farm_id', 'crop_type_id']);
        });
    }

    public function down(): void
    {
        DB::table('farm_crop_type_overrides')->whereNotNull('unit')->delete();

        Schema::table('farm_crop_type_overrides', function (Blueprint $table): void {
            $table->unique(['farm_id', 'crop_type_id']);
        });

        Schema::table('farm_crop_type_overrides', function (Blueprint $table): void {
            $table->dropUnique('farm_overrides_farm_crop_unit_unique');
            $table->dropColumn('unit');
        });

        Schema::dropIfExists('crop_type_unit_guards');
    }
};
