<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tawad_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();

            // App\Enums\TawadType. Exactly two types, never a third.
            $table->string('type');

            // Pesos. There is no percentage input anywhere in this system.
            $table->decimal('discount_amount', 10, 2);

            // Required when type is min_quantity, null for flat.
            $table->decimal('min_quantity', 10, 2)->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['listing_id', 'is_active']);
        });

        if (Schema::getConnection()->getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE tawad_rules ADD CONSTRAINT chk_tawad_discount_positive CHECK (discount_amount > 0)');
            DB::statement('ALTER TABLE tawad_rules ADD CONSTRAINT chk_tawad_min_quantity CHECK (min_quantity IS NULL OR min_quantity > 0)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tawad_rules');
    }
};
