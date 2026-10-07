<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stall_conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('buyer_cleared_through_message_id')->nullable();
            $table->unsignedBigInteger('farmer_seller_cleared_through_message_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('stall_conversations', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_cleared_through_message_id',
                'farmer_seller_cleared_through_message_id',
            ]);
        });
    }
};
