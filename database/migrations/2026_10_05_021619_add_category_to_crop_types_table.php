<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crop_types', function (Blueprint $table): void {
            $table->string('category')->default('fresh_produce')->index();
        });
    }

    public function down(): void
    {
        Schema::table('crop_types', function (Blueprint $table): void {
            $table->dropIndex(['category']);
            $table->dropColumn('category');
        });
    }
};
