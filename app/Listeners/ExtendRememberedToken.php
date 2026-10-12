<?php

namespace App\Listeners;

use Laravel\Sanctum\Events\TokenAuthenticated;

/**
 * A "remember me" login lasts remember_days from the last time the app was
 * used, not from the login, so someone using the app every day is never
 * logged out. Short session tokens (Remember me off) are left alone.
 */
class ExtendRememberedToken
{
    public function handle(TokenAuthenticated $event): void
    {
        $token = $event->token;

        if ($token->expires_at === null || $token->created_at === null) {
            return;
        }

        $sessionHours = (int) config('anihow.auth.session_hours');
        $rememberDays = (int) config('anihow.auth.remember_days');

        if ($token->created_at->diffInHours($token->expires_at) <= $sessionHours) {
            return;
        }

        $renewed = now()->addDays($rememberDays);

        // Write at most about once a day.
        if ($token->expires_at->greaterThan($renewed->copy()->subDay())) {
            return;
        }

        $token->forceFill(['expires_at' => $renewed])->save();
    }
}
