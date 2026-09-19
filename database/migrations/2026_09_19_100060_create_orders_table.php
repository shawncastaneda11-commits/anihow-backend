<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // Human readable, quoted at handover.
            $table->string('order_number', 20)->unique();

            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('farmer_seller_id')->constrained('users')->restrictOnDelete();

            // Denormalized for farm-scoped analytics.
            $table->foreignId('farm_id')->constrained()->restrictOnDelete();

            // App\Enums\OrderStatus
            $table->string('status')->default('placed');

            // App\Enums\FulfillmentPreference. A text arrangement between the
            // two parties. No courier, no fee, no tracking number, no route.
            $table->string('fulfillment_preference');
            $table->text('fulfillment_note')->nullable();

            // Recorded, never processed. Single value by design; kept as a
            // column so the constraint is visible rather than assumed.
            $table->string('payment_method')->default('cash_on_handover');

            $table->decimal('subtotal', 12, 2);
            $table->decimal('tawad_total', 12, 2)->default(0);
            $table->decimal('total', 12, 2);

            // Cash counted at handover. Required to reach Completed.
            $table->decimal('amount_received', 12, 2)->nullable();

            // Seller's internal note. Distinct from fulfillment_note.
            $table->text('notes')->nullable();

            // App\Enums\CancellationReason. A no-show is a cancellation
            // reason, not a sixth status.
            $table->string('cancellation_reason')->nullable();

            // App\Enums\OrderActor
            $table->string('cancelled_by')->nullable();
            $table->string('cancellation_note')->nullable();

            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['farmer_seller_id', 'status']);
            $table->index(['farm_id', 'completed_at']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
