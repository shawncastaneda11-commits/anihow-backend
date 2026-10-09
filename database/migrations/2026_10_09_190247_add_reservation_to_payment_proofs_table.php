<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A proof belongs to an order or a reservation. Conversion also sets order_id
 * on the accepted reservation proof, so both may be filled afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('payment_proofs', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
