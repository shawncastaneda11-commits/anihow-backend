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

    public const FAILURES_PREFIX = 'password-reset-otp-failures:';

    public const COOLDOWN_PREFIX = 'password-reset-otp-cooldown:';

    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public const MAX_FAILURES = 10;

    public const COOLDOWN_SECONDS = 60;

    public const FAILURE_WINDOW_MINUTES = 60;

    /**
     * Send one code, or send nothing when this account is cooling down
     * or locked out. The failure budget is left untouched.
     */
    public function handle(User $user): ?string
    {
        if ($this->isLocked($user)) {
            return null;
        }

        if (! Cache::add(self::COOLDOWN_PREFIX.$user->getKey(), 1, self::COOLDOWN_SECONDS)) {
            return null;
        }

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$user->getKey(), Hash::make($code), now()->addMinutes(self::TTL_MINUTES));

        try {
            $user->notifyNow(new ResetPasswordCodeNotification($code));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $code;
    }

    /**
     * True when the code matches the cached hash.
     * Five wrong attempts invalidate the current code.
     * Ten wrong codes in an hour lock the account.
     */
    public function matches(User $user, string $code): bool
    {
        if ($this->isLocked($user)) {
            return false;
        }

        $key = self::CACHE_PREFIX.$user->getKey();
        $hash = Cache::get($key);

        if (! is_string($hash) || ! Hash::check($code, $hash)) {
            $this->recordFailure($user);

            if (is_string($hash)) {
                $this->recordCodeAttempt($user, $key);
            }

            return false;
        }

        return true;
    }

    public function forget(User $user): void
    {
        $id = $user->getKey();

        Cache::forget(self::CACHE_PREFIX.$id);
        Cache::forget(self::ATTEMPTS_PREFIX.$id);
        Cache::forget(self::FAILURES_PREFIX.$id);
        Cache::forget(self::COOLDOWN_PREFIX.$id);
    }

    private function isLocked(User $user): bool
    {
        return (int) Cache::get(self::FAILURES_PREFIX.$user->getKey(), 0) >= self::MAX_FAILURES;
    }

    private function recordFailure(User $user): void
    {
        $this->incrementCounter(
            self::FAILURES_PREFIX.$user->getKey(),
            self::FAILURE_WINDOW_MINUTES * 60,
        );
    }

    private function recordCodeAttempt(User $user, string $codeKey): void
    {
        $attempts = $this->incrementCounter(
            self::ATTEMPTS_PREFIX.$user->getKey(),
            self::TTL_MINUTES * 60,
        );

        if ($attempts >= self::MAX_ATTEMPTS) {
            Cache::forget($codeKey);
            Cache::forget(self::ATTEMPTS_PREFIX.$user->getKey());
        }
    }

    /**
     * Add the key once so its expiry sticks, then increment it.
     * A second caller finds the key already present and cannot reset the window.
     */
    private function incrementCounter(string $key, int $ttlSeconds): int
    {
        $added = Cache::add($key, 0, $ttlSeconds);
        $hits = (int) Cache::increment($key);

        if (! $added && $hits === 1) {
            Cache::put($key, 1, $ttlSeconds);
        }

        return $hits;
    }
}
