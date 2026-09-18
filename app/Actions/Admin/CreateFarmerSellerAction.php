<?php

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Models\User;

class CreateFarmerSellerAction
{
    /**
     * Farmer-seller accounts are created (and thereby verified) by super_admin only.
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null, location?: string|null, shop_name?: string|null, bio?: string|null, contact?: string|null, is_active?: bool}  $data
     */
    public function handle(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'shop_name' => $data['shop_name'] ?? null,
            'bio' => $data['bio'] ?? null,
            'contact' => $data['contact'] ?? ($data['phone'] ?? null),
            'is_active' => $data['is_active'] ?? true,
            // Admin-created farmer_seller accounts are treated as verified.
            'email_verified_at' => now(),
        ]);

        $user->assignRole(Role::FarmerSeller);

        return $user->load('roles');
    }
}
