<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('listing_id')->nullable()->constrained('listings')->nullOnDelete();
            $table->foreignId('farmer_seller_id')->constrained('users');
            $table->foreignId('farm_id')->nullable()->constrained('farms')->nullOnDelete();
            $table->decimal('quantity', 10, 2);
            $table->string('unit');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('line_subtotal', 12, 2);
            $table->decimal('tawad_amount', 10, 2)->default(0);
            $table->decimal('line_total', 12, 2);
            $table->foreignId('crop_type_id')->nullable()->constrained('crop_types')->nullOnDelete();
            $table->string('listing_name');
            $table->foreignId('tawad_rule_id')->nullable()->constrained('tawad_rules')->nullOnDelete();
            $table->string('tawad_type')->nullable();
            $table->string('fulfillment_preference')->default('buyer_pickup');
            $table->text('fulfillment_note')->nullable();
            $table->string('status');
            $table->string('cancellation_reason')->nullable();
            $table->string('cancellation_note')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('converted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('active_slot')->nullable()->unique();
            $table->timestamps();

            $table->index(['listing_id', 'status']);
            $table->index(['buyer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
