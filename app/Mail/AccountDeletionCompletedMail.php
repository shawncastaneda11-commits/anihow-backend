<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class AccountDeletionCompletedMail extends Mailable
{
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
