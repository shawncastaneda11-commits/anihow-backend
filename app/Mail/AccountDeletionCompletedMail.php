<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccountDeletionCompletedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public string $recipientName) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your AniHow account has been deleted',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.account-deletion-completed',
        );
    }
}
