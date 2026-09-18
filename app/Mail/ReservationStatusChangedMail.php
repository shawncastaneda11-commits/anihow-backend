<?php

namespace App\Mail;

use App\Models\Reservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationStatusChangedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Reservation $reservation) {}

    public function envelope(): Envelope
    {
        $status = $this->reservation->status?->label() ?? 'updated';

        return new Envelope(
            subject: "AniHow reservation {$status}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.reservation-status-changed',
        );
    }
}
