<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Gives the ICTD Super Admin the System health page (Permission::
 * ManageSystemSettings). The seeder syncs every role, so it is safe to repeat.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function down(): void {}
};
