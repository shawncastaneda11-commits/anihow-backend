<?php

namespace App\Notifications;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification
{
    public function __construct(public string $code) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = SendEmailVerificationCodeAction::TTL_MINUTES;

        return (new MailMessage)
            // The code stays out of the subject: subjects show in inbox lists, phone lock screens and
            // notification previews, where anyone nearby could read and use it.
            ->subject('Your AniHow verification code / Ang verification code mo sa AniHow')
            ->line('Your AniHow verification code is:')
            ->line('Ang verification code mo sa AniHow ay:')
            ->line($this->code)
            ->line("Open the AniHow app and type this 6-digit code. It expires in {$minutes} minutes.")
            ->line("Buksan ang AniHow at i-type ang 6 na digit. May bisa ito ng {$minutes} minuto.")
            ->line('If you did not create an AniHow account, ignore this email.')
            ->line('Kung hindi ikaw ang gumawa ng account, huwag pansinin ang email na ito.');
    }
}
