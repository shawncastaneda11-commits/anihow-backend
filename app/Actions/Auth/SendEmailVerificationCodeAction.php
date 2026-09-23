<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class SendEmailVerificationCodeAction
{
    public const CACHE_PREFIX = 'email-otp:';

    public const TTL_MINUTES = 10;

    public function handle(User $user): void
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$user->getKey(), Hash::make($code), now()->addMinutes(self::TTL_MINUTES));

        $user->notify(new VerifyEmailNotification($code));
    }
}
