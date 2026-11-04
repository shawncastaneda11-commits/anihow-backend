<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The maximum tawad becomes optional. Blank means no maximum: the floor alone
 * limits a tawad. Existing values are kept as they are, so nothing changes
 * until a Super Admin or Content Editor clears a maximum. Zero still turns
 * tawad off for the crop.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->dropCheck();

        Schema::table('crop_types', function (Blueprint $table): void {
            $table->decimal('max_discount', 12, 4)->nullable()->default(null)->change();
        });

        $this->addCheck('max_discount IS NULL OR (max_discount >= 0 AND max_discount < floor_price)');
    }

    public function down(): void
    {
        $this->dropCheck();

        DB::table('crop_types')->whereNull('max_discount')->update(['max_discount' => 0]);

        Schema::table('crop_types', function (Blueprint $table): void {
            $table->decimal('max_discount', 12, 4)->default(0)->change();
        });

        $this->addCheck('max_discount >= 0 AND max_discount < floor_price');
    }

    /**
     * MySQL refuses to change a column that a CHECK constraint names. SQLite
     * never received these constraints, the same driver check as the original
     * migration. The floor-positive check does not name max_discount and stays.
     */
    private function dropCheck(): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE crop_types DROP CONSTRAINT chk_crop_types_discount_below_floor');
    }

    private function addCheck(string $condition): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE crop_types ADD CONSTRAINT chk_crop_types_discount_below_floor CHECK ({$condition})");
    }
};
