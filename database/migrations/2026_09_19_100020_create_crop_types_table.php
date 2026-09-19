<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crop_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Bilingual scope is crop labels only. Do not widen.
            $table->string('label_en');
            $table->string('label_fil');

            $table->text('description')->nullable();

            // App\Enums\ListingUnit. One crop, one unit, system-wide.
            $table->string('unit_of_measure');

            // Super Admin only. No other role may write these two columns.
            $table->decimal('floor_price', 10, 2);
            $table->decimal('max_discount', 10, 2)->default(0);

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
        });

        // Belt and braces behind the FormRequest rules. MySQL 8.0.16+ enforces
        // CHECK; older MySQL and some MariaDB builds parse and ignore it.
        DB::statement('ALTER TABLE crop_types ADD CONSTRAINT chk_crop_types_floor_positive CHECK (floor_price > 0)');
        DB::statement('ALTER TABLE crop_types ADD CONSTRAINT chk_crop_types_discount_below_floor CHECK (max_discount >= 0 AND max_discount < floor_price)');
    }

    public function down(): void
    {
        Schema::dropIfExists('crop_types');
    }
};
