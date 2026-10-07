<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stall_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stall_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['stall_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stall_messages');
    }
};
