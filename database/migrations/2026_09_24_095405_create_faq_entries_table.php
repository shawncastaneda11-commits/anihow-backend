<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('intent_key');
            $table->json('roles');
            $table->string('label');
            $table->string('label_fil');
            $table->json('keywords');
            $table->text('answer');
            $table->text('answer_fil');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['farm_id', 'intent_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_entries');
    }
};
