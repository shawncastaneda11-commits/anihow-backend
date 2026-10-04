<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->date('harvested_on')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table): void {
            $table->dropColumn(['available_from', 'available_until', 'harvested_on']);
        });
    }
};
