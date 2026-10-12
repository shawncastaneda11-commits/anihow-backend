<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing accounts keep requires_id_verification = false, so they are
     * never blocked. Only buyers who sign up after this change must verify.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('requires_id_verification')->default(false);
            $table->timestamp('id_verified_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['requires_id_verification', 'id_verified_at']);
        });
    }
};
