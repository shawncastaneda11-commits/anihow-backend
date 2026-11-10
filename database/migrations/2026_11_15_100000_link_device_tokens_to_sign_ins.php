<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A phone's push registration belongs to the sign-in that made it, so the
 * phone stops getting pushes when that sign-in ends: signing out, signing
 * out everywhere, suspension, a password reset or change, or expiry.
 * Phones registered before this are linked the next time the app opens.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->foreignId('personal_access_token_id')
                ->nullable()
                ->after('user_id')
                ->constrained('personal_access_tokens')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('device_tokens', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('personal_access_token_id');
        });
    }
};
