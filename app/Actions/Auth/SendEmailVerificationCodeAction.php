<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SendEmailVerificationCodeAction
{
    public const CACHE_PREFIX = 'email-otp:';

    public const TTL_MINUTES = 10;

    public function handle(User $user): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        Cache::put(self::CACHE_PREFIX.$user->getKey(), Hash::make($code), now()->addMinutes(self::TTL_MINUTES));

        try {
            $user->notifyNow(new VerifyEmailNotification($code));
        } catch (\Throwable $exception) {
            report($exception);
        }

        return $code;
    }

    /**
     * A real outbound mailer is configured (Gmail SMTP with both
     * username and password, or a hosted mailer).
     */
    public static function isMailConfigured(): bool
    {
        $mailer = (string) config('mail.default');

        if (in_array($mailer, ['ses', 'postmark', 'resend', 'mailgun'], true)) {
            return true;
        }

        if ($mailer === 'smtp') {
            return filled(config('mail.mailers.smtp.username'))
                && filled(config('mail.mailers.smtp.password'));
        }

        return false;
    }

    /**
     * Show the code in the JSON body only on local/testing when no
     * outbound mailer is configured. Never in any other environment.
     */
    public static function shouldExposeCode(): bool
    {
        return app()->environment(['local', 'testing']) && ! self::isMailConfigured();
    }

    /**
     * Staging/production must deliver the OTP by email. If mail is not
     * configured there, register and resend fail instead of leaking the code.
     */
    public static function mailRequiredButMissing(): bool
    {
        return ! app()->environment(['local', 'testing']) && ! self::isMailConfigured();
    }

    public static function unavailableMessage(): string
    {
        return 'Email verification is temporarily unavailable. Please try again later.';
    }

    public static function logUnavailable(): void
    {
        Log::error('Email verification is unavailable because outbound mail is not configured.');
    }
}
