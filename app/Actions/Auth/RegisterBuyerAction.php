<?php

namespace App\Actions\Auth;

use App\Enums\Role;
use App\Enums\UserStatus;
use App\Models\User;

class RegisterBuyerAction
{
    /**
     * Self-registration is allowed for buyers only.
     * Farmer-seller accounts must be created by a super_admin.
     *
     * @param  array{name: string, email: string, password: string, phone?: string|null}  $data
     * @return array{user: User, verification_code: string}
     */
    public function handle(array $data): array
    {
        $user = User::query()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'phone' => $data['phone'] ?? null,
            'status' => UserStatus::Active,
            'email_verified_at' => null,
        ]);

        $user->assignRole(Role::Buyer);
        $code = app(SendEmailVerificationCodeAction::class)->handle($user);

        return [
            'user' => $user->load('roles'),
            'verification_code' => $code,
        ];
    }
}
