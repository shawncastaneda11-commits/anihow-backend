<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class FarmerSellerSeeder extends Seeder
{
    public function run(): void
    {
        $farmers = [
            [
                'name' => 'Juan Dela Cruz',
                'email' => 'juan@anihow.local',
                'phone' => '09171230001',
                'location' => 'San Francisco, General Trias, Cavite',
                'shop_name' => 'Juan\'s Farm Stall',
                'bio' => 'Morning harvest from San Francisco plots. Tomatoes, ampalaya, and sitaw.',
                'contact' => '09171230001',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'maria.santos@anihow.local',
                'phone' => '09181230002',
                'location' => 'Tejero, General Trias, Cavite',
                'shop_name' => 'Santos Fruit Corner',
                'bio' => 'Backyard Carabao mangoes and Lakatan from Tejero.',
                'contact' => '09181230002',
            ],
            [
                'name' => 'Pedro Reyes',
                'email' => 'pedro.reyes@anihow.local',
                'phone' => '09201230003',
                'location' => 'Navarro, General Trias, Cavite',
                'shop_name' => 'Reyes Root Crops',
                'bio' => 'Kamote and palay from Navarro. Farm-gate pickup welcome.',
                'contact' => '09201230003',
            ],
        ];

        foreach ($farmers as $farmer) {
            $user = User::query()->updateOrCreate(
                ['email' => $farmer['email']],
                [
                    ...$farmer,
                    'password' => 'password',
                    'status' => UserStatus::Active,
                    'approved_at' => now(),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles(Role::FarmerSeller);
        }
    }
}
