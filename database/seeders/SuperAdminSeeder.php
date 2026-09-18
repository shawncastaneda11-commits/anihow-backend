<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::query()->updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@anihow.local')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'AniHow Super Admin'),
                'password' => env('SUPER_ADMIN_PASSWORD', 'password'),
                'phone' => env('SUPER_ADMIN_PHONE'),
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );

        $user->syncRoles(Role::SuperAdmin);
    }
}
