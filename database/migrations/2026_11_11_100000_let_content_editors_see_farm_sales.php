<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives Content Editors their own farm's orders, reservations and payment
 * status, read-only (Permission::ViewOwnFarmOrders). The seeder syncs every
 * role, so it is safe to repeat.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function down(): void {}
};
