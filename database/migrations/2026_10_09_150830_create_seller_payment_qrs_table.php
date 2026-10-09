<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_payment_qrs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('wallet');
            $table->string('account_name', 80);
            $table->char('account_last4', 4);
            $table->string('image_path');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payment_qrs');
    }
};
