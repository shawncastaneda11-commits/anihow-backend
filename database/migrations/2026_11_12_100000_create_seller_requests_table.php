<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Content Editor asking COSEL to add a farmer-seller to their farm. COSEL
 * decides: "added" creates the account, "not added" carries a reason the
 * Content Editor sees. Also gives Content Editors the permission to ask.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seller_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('note', 500)->nullable();
            $table->string('status', 20)->default('waiting');
            $table->string('decision_reason', 500)->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['farm_id', 'status']);
        });

        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_requests');
    }
};
