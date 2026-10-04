<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropCropTypeChecks();

        Schema::table('crop_types', function (Blueprint $table): void {
            $table->decimal('floor_price', 12, 4)->change();
            $table->decimal('max_discount', 12, 4)->default(0)->change();
        });

        Schema::table('farm_crop_type_overrides', function (Blueprint $table): void {
            $table->decimal('floor_price', 12, 4)->nullable()->change();
            $table->decimal('max_discount', 12, 4)->nullable()->change();
        });

        $this->addCropTypeChecks();
    }

    public function down(): void
    {
        $this->dropCropTypeChecks();

        Schema::table('crop_types', function (Blueprint $table): void {
            $table->decimal('floor_price', 10, 2)->change();
            $table->decimal('max_discount', 10, 2)->default(0)->change();
        });

        Schema::table('farm_crop_type_overrides', function (Blueprint $table): void {
            $table->decimal('floor_price', 10, 2)->nullable()->change();
            $table->decimal('max_discount', 10, 2)->nullable()->change();
        });

        $this->addCropTypeChecks();
    }

    /**
     * MySQL refuses to change a column that a CHECK constraint names. SQLite
     * never received these constraints; ALTER TABLE ... DROP CONSTRAINT is
     * rejected there, so the same driver check as the original migration applies.
     */
    private function dropCropTypeChecks(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE crop_types DROP CONSTRAINT chk_crop_types_floor_positive');
        DB::statement('ALTER TABLE crop_types DROP CONSTRAINT chk_crop_types_discount_below_floor');
    }

    private function addCropTypeChecks(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE crop_types ADD CONSTRAINT chk_crop_types_floor_positive CHECK (floor_price > 0)');
        DB::statement('ALTER TABLE crop_types ADD CONSTRAINT chk_crop_types_discount_below_floor CHECK (max_discount >= 0 AND max_discount < floor_price)');
    }
};
