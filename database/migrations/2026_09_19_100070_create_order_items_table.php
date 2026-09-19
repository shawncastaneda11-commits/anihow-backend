<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();

            // Reference only. A deleted listing must not take the order record
            // with it, which is why every display value below is snapshotted.
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();

            // Analytics groups by crop type, so this link must survive.
            $table->foreignId('crop_type_id')->constrained()->restrictOnDelete();

            $table->string('listing_name');
            $table->string('unit');
            $table->decimal('quantity', 10, 2);

            // The listed price. Never overwritten by a tawad.
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_subtotal', 12, 2);

            $table->foreignId('tawad_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tawad_type')->nullable();
            $table->decimal('tawad_amount', 10, 2)->default(0);

            // line_subtotal - tawad_amount
            $table->decimal('line_total', 12, 2);

            $table->timestamps();

            $table->index('crop_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
