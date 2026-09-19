<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Separate from create_users_table because farms must exist first and the
     * users migration is pinned to 0001_01_01 by the framework.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Required for content_editor and farmer_seller, null for
            // super_admin and buyer. Enforced in validation, not in the schema.
            $table->foreignId('farm_id')->nullable()->after('location')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('farm_id');
        });
    }
};
