<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class BuyerSeeder extends Seeder
{
    public function run(): void
    {
        $buyers = [
            [
                'name' => 'Ana Reyes',
                'email' => 'ana.buyer@anihow.local',
                'phone' => '09190001111',
            ],
            [
                'name' => 'Ben Cruz',
                'email' => 'ben.buyer@anihow.local',
                'phone' => '09200002222',
            ],
        ];

        foreach ($buyers as $buyer) {
            $user = User::query()->updateOrCreate(
                ['email' => $buyer['email']],
                [
                    ...$buyer,
                    'password' => 'password',
                    'status' => UserStatus::Active,
                    'approved_at' => now(),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles(Role::Buyer);
        }
    }
}
