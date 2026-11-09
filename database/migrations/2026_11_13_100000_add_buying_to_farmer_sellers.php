<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A farmer-seller can also buy with the same account. These record when
 * buying was turned on and whether the seller did it in the app or COSEL
 * did it in the CMS; the buyer role itself carries the permission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('buying_enabled_at')->nullable()->after('requires_id_verification');
            $table->string('buying_enabled_via', 10)->nullable()->after('buying_enabled_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['buying_enabled_at', 'buying_enabled_via']);
        });
    }
};
