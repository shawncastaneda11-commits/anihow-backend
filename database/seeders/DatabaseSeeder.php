<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Roles and permissions always. Smoke fixtures (and listing cover photos
     * that hang off those listings) only on local and testing, so
     * `migrate:fresh --seed` gives the Flutter live tests their accounts.
     * Never in production: those seeders walk a real checkout and write demo users.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            FaqEntrySeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                SuperAdminSeeder::class,
                SmokeTestSeeder::class,
                ListingImageSeeder::class,
            ]);
        }
    }
}
