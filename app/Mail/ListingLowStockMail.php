<?php

namespace App\Mail;

use App\Models\Listing;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ListingLowStockMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Listing $listing) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Low stock: {$this->listing->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.listing-low-stock',
        );
    }
}
