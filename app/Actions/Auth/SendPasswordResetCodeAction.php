<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\ResetPasswordCodeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

class SendPasswordResetCodeAction
{
    public const CACHE_PREFIX = 'password-reset-otp:';

    public const ATTEMPTS_PREFIX = 'password-reset-otp-attempts:';

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function handle(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$user->getKey(), Hash::make($code), now()->addMinutes(self::TTL_MINUTES));
        Cache::forget(self::ATTEMPTS_PREFIX.$user->getKey());

        try {
            $user->notifyNow(new ResetPasswordCodeNotification($code));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $code;
    }

    /**
     * True when the code matches the cached hash.
     * Five wrong attempts invalidate the code. A later check fails.
     */
    public function matches(User $user, string $code): bool
    {
        $key = self::CACHE_PREFIX.$user->getKey();
        $hash = Cache::get($key);

        if (! is_string($hash)) {
            return false;
        }

        if (Hash::check($code, $hash)) {
            return true;
        }

        $attemptsKey = self::ATTEMPTS_PREFIX.$user->getKey();
        $attempts = (int) Cache::get($attemptsKey, 0) + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($key);
            Cache::forget($attemptsKey);

            return false;
        }

        Cache::put($attemptsKey, $attempts, now()->addMinutes(self::TTL_MINUTES));

        return false;
    }

    public function forget(User $user): void
    {
        Cache::forget(self::CACHE_PREFIX.$user->getKey());
        Cache::forget(self::ATTEMPTS_PREFIX.$user->getKey());
    }
}
