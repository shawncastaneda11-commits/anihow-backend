<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('farmer_seller_id')->constrained('users')->restrictOnDelete();
            $table->string('status');
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('cancelled_by')->nullable();
            $table->timestamp('ready_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['buyer_id', 'status']);
            $table->index(['farmer_seller_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
