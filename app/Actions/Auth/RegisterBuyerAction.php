<?php

namespace App\Actions\Auth;

use App\Enums\Role;
use App\Models\User;

class RegisterBuyerAction
{
    /**
     * Self-registration is allowed for buyers only.
     * Farmer-seller accounts must be created by a super_admin.
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $data
     */
    public function handle(array $data): User
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'is_active' => true,
            'email_verified_at' => null,
        ]);

        $user->assignRole(Role::Buyer);
        $user->sendEmailVerificationNotification();

        return $user->load('roles');
    }
}
