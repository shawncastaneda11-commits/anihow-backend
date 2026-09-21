<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change B: walk-in sales.
 *
 * A walk-in is a sale the farmer-seller records after an in-person handover
 * with someone who has no buyer account. It lands in the same orders table as
 * every app order, directly at Completed.
 *
 *   buyer_id            becomes nullable. A walk-in has no buyer account.
 *   source              app or walk_in. Explicit, so a null buyer_id is never
 *                       the only thing marking a walk-in. Decision 19.
 *   walk_in_buyer_name  optional, for the seller's own reference only.
 *                       Decision 20.
 *
 * No CHECK constraint ties source to buyer_id. Raw ALTER TABLE ADD CONSTRAINT
 * does not run on SQLite, and the rule is enforced where the walk-in is
 * created, the same way farm price guards are.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The foreign key comes off first so the column can be altered, then
        // goes back on unchanged.
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_id')->nullable()->change();
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();

            $table->string('source')->default('app')->after('status');
            $table->string('walk_in_buyer_name', 100)->nullable()->after('source');
        });
    }

    public function down(): void
    {
        // Rolling back would leave walk-in orders with no buyer in a column that
        // no longer allows it. Refuse rather than delete sales records.
        if (DB::table('orders')->whereNull('buyer_id')->exists()) {
            throw new RuntimeException(
                'Walk-in orders exist. Rolling back needs a buyer on every order; '
                .'resolve those records first.'
            );
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['source', 'walk_in_buyer_name']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['buyer_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_id')->nullable(false)->change();
            $table->foreign('buyer_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
