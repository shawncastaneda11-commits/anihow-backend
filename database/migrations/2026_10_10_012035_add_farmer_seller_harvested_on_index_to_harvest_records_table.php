<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('harvest_records', function (Blueprint $table): void {
            $table->index(['farmer_seller_id', 'harvested_on']);
        });
    }

    public function down(): void
    {
        Schema::table('harvest_records', function (Blueprint $table): void {
            $table->dropIndex(['farmer_seller_id', 'harvested_on']);
        });
    }
};
