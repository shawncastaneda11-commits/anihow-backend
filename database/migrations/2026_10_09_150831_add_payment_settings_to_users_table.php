<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online payment starts off. Anyone without a QR (every account at deploy)
     * is switched off so buyers are not offered a pay button with nothing to scan.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('payment_time_limit_hours')->default(24);
        });

        DB::table('users')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('seller_payment_qrs')
                    ->whereColumn('seller_payment_qrs.farmer_seller_id', 'users.id')
                    ->whereNull('seller_payment_qrs.deleted_at');
            })
            ->update(['accepts_online_payment' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepts_online_payment')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('accepts_online_payment')->default(true)->change();
            $table->dropColumn('payment_time_limit_hours');
        });
    }
};
