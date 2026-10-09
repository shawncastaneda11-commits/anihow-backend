<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Online orders placed before proof tracking stay untracked. Cash stays null.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status')->nullable();
            $table->timestamp('payment_due_at')->nullable();
            $table->timestamp('payment_reminded_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('payment_qr_ids')->nullable();
            $table->string('refund_reference', 40)->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
        });

        DB::table('orders')
            ->where('payment_method', 'online_transfer')
            ->update(['payment_status' => 'not_tracked']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('refunded_by');
            $table->dropColumn([
                'payment_status',
                'payment_due_at',
                'payment_reminded_at',
                'paid_at',
                'payment_qr_ids',
                'refund_reference',
                'refunded_at',
            ]);
        });
    }
};
