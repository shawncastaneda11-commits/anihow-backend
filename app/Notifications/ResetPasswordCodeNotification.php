<?php

namespace App\Notifications;

use App\Actions\Auth\SendPasswordResetCodeAction;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordCodeNotification extends Notification
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
        $minutes = SendPasswordResetCodeAction::TTL_MINUTES;

        return (new MailMessage)
            ->subject('Your AniHow password reset code')
            ->line("Your AniHow password reset code is {$this->code}. It expires in {$minutes} minutes.")
            ->line("Ang password reset code mo sa AniHow ay {$this->code}. May bisa ito ng {$minutes} minuto.")
            ->line('If you did not ask to reset your password, ignore this email.')
            ->line('Kung hindi ikaw ang humiling nito, huwag pansinin ang email na ito.');
    }
}
