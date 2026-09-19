<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Reduced to roles and permissions for the marketplace rebuild. The
     * remaining seeders reference dropped tables and changed columns; they
     * are rewritten alongside the models.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
        ]);
    }
}
