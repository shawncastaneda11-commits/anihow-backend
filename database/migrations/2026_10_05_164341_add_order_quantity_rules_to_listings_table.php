<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing listings sell whole units: minimum 1, step 1.
     */
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->decimal('min_order_quantity', 10, 2)->default(1);
            $table->decimal('order_step', 10, 2)->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropColumn(['min_order_quantity', 'order_step']);
        });
    }
};
