<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farmer_seller_id')->constrained('users')->restrictOnDelete();
            $table->decimal('total', 12, 2);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('farmer_seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
