<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Takes orders, payments and reservations away from the ICTD Super Admin:
 * they hold people's details and payment screenshots, which are COSEL's.
 * The seeder syncs every role, so it is safe to repeat.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function down(): void {}
};
