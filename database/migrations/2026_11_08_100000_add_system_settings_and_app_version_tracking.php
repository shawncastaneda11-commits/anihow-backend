<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ICTD's system settings (minimum app version, maintenance notice, required
 * two-step sign-in), the app version each user last opened AniHow with, and
 * the flag for two-step sign-in by email code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('has_email_authentication')->default(false);
            $table->string('last_app_version', 20)->nullable();
            $table->timestamp('last_app_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['has_email_authentication', 'last_app_version', 'last_app_seen_at']);
        });

        Schema::dropIfExists('system_settings');
    }
};
