<?php

use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Splits administration between two offices.
 *
 * COSEL gets the new Account Manager role: people's accounts, ID checks,
 * deletion requests, partner farms, and moderation. The ICTD Super Admin keeps
 * the technical side and now manages administrator accounts only. Content
 * Editors may view and edit their own farm's listings.
 *
 * The seeder syncs every role to App\Enums\Permission::forRole(), so running
 * it here is the whole change and is safe to repeat. No account changes role:
 * after deploying, the Super Admin creates the COSEL account(s).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolePermissionSeeder)->run();
    }

    public function down(): void
    {
        // The previous permission sets are in git history; re-run the seeder
        // from that version to restore them.
    }
};
