<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('buyer_id')->constrained('users');
            $table->foreignId('farmer_seller_id')->constrained('users');
            $table->foreignId('seller_payment_qr_id')->nullable()->constrained('seller_payment_qrs')->nullOnDelete();
            $table->string('reference_number', 40);
            $table->decimal('amount', 12, 2);
            $table->string('screenshot_path')->nullable();
            $table->timestamp('screenshot_deleted_at')->nullable();
            $table->string('status');
            $table->string('rejection_reason')->nullable();
            $table->string('rejection_note', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('reminder_12h_at')->nullable();
            $table->timestamp('reminder_24h_at')->nullable();
            $table->timestamps();

            $table->index(['farmer_seller_id', 'reference_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_proofs');
    }
};
