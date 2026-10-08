<?php

namespace App\Actions\Admin;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Str;

class CreateFarmerSellerAction
{
    /**
     * Farmer-seller accounts are created (and thereby verified) by super_admin only.
     * Any password sent by the client is ignored. The temporary password is returned
     * once and is not stored in plain text.
     *
     * @param  array{name: string, email: string, phone?: string|null, location?: string|null, shop_name?: string|null, bio?: string|null, contact?: string|null}  $data
     * @return array{user: User, temporary_password: string}
     */
    public function handle(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Str::password(32),
            'phone' => $data['phone'] ?? null,
            'location' => $data['location'] ?? null,
            'shop_name' => $data['shop_name'] ?? null,
            'bio' => $data['bio'] ?? null,
            'contact' => $data['contact'] ?? ($data['phone'] ?? null),
            'status' => UserStatus::Pending,
            // Admin-created farmer_seller accounts are treated as verified.
            'email_verified_at' => now(),
        ]);

        $user->assignRole(Role::FarmerSeller);
        $temporaryPassword = $user->issueTemporaryPassword();

        return [
            'user' => $user->load('roles'),
            'temporary_password' => $temporaryPassword,
        ];
    }
}
