<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LoginUserAction
{
    /**
     * Authenticate any active role (super_admin, farmer_seller, buyer)
     * and issue a Sanctum API token for mobile / API clients.
     *
     * @return array{user: User, token: string}
     */
    public function handle(string $email, string $password, string $deviceName = 'mobile'): array
    {
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'email' => 'This account is inactive. Contact the AniHow administrator.',
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }
}
