<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crop_types', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->foreignId('farm_id')->nullable()->after('id')->constrained()->restrictOnDelete();
            $table->unique(['farm_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('crop_types', function (Blueprint $table) {
            $table->dropUnique(['farm_id', 'slug']);
            $table->dropConstrainedForeignId('farm_id');
            $table->unique('slug');
        });
    }
};
