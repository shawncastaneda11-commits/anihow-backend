<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One of order_id or reservation_id is set. Conversion writes a separate
 * event on the new order and leaves the reservation events in place.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payment_events', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('order_payment_events', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable()->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->foreignId('reservation_id')->nullable()->constrained('reservations')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('order_payment_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reservation_id');
        });

        Schema::table('order_payment_events', function (Blueprint $table) {
            $table->dropForeign(['order_id']);
        });

        Schema::table('order_payment_events', function (Blueprint $table) {
            $table->unsignedBigInteger('order_id')->nullable(false)->change();
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
        });
    }
};
