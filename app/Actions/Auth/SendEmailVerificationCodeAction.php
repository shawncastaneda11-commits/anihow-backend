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
     * Show the code in the app only when Gmail/SMTP is not ready.
     */
    public static function shouldExposeCode(): bool
    {
        return ! self::isMailConfigured();
    }
}
