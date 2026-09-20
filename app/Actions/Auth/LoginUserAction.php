<?php

namespace App\Actions\Auth;

use App\Enums\UserStatus;
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

        if (! $user->status->canAuthenticate()) {
            throw ValidationException::withMessages([
                'email' => match ($user->status) {
                    UserStatus::Pending => 'Your account is awaiting approval. You can sign in after an administrator approves it.',
                    default => 'This account is suspended. Contact the AniHow administrator.',
                },
            ]);
        }

        $token = $user->createToken($deviceName)->plainTextToken;

        return [
            'user' => $user->load('roles'),
            'token' => $token,
        ];
    }
}
