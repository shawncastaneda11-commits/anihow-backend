<?php

namespace App\Filament\Auth;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    public function authenticate(): ?LoginResponse
    {
        $data = $this->form->getState();
        $password = (string) ($data['password'] ?? '');
        $user = User::query()->where('email', (string) ($data['email'] ?? ''))->first();

        if (
            $user instanceof User
            && $password !== ''
            && Hash::check($password, $user->password)
            && $user->hasExpiredTemporaryPassword()
        ) {
            throw ValidationException::withMessages([
                'data.email' => User::EXPIRED_TEMPORARY_PASSWORD_MESSAGE,
            ]);
        }

        return parent::authenticate();
    }
}
