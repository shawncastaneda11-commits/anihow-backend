<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->decimal('price_per_unit', 12, 4)->change();
        });

        Schema::table('tawad_rules', function (Blueprint $table): void {
            $table->decimal('discount_amount', 12, 4)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_price', 12, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->decimal('price_per_unit', 10, 2)->change();
        });

        Schema::table('tawad_rules', function (Blueprint $table): void {
            $table->decimal('discount_amount', 10, 2)->change();
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->decimal('unit_price', 10, 2)->change();
        });
    }
};
