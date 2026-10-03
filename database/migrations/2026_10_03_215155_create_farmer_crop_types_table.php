<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A farmer-seller's own crop list. No rows means they may use every crop
     * type. Once a row exists, new listings may use only this list.
     */
    public function up(): void
    {
        Schema::create('farmer_crop_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crop_type_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'crop_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('farmer_crop_types');
    }
};
