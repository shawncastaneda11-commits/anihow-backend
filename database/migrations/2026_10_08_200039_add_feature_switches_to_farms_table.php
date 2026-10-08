<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->boolean('value_added_enabled')->default(true);
            $table->boolean('reservations_enabled')->default(true);
            $table->boolean('tawad_enabled')->default(true);
            $table->boolean('walk_in_enabled')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('farms', function (Blueprint $table) {
            $table->dropColumn([
                'value_added_enabled',
                'reservations_enabled',
                'tawad_enabled',
                'walk_in_enabled',
            ]);
        });
    }
};
