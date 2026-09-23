<?php

namespace App\Notifications;

use App\Actions\Auth\SendEmailVerificationCodeAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VerifyEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
        return (new MailMessage)
            ->subject('Your AniHow verification code')
            ->line('Your email verification code is '.$this->code.'.')
            ->line('This code expires in '.SendEmailVerificationCodeAction::TTL_MINUTES.' minutes.');
    }
}
