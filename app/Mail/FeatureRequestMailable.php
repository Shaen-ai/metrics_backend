<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class FeatureRequestMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $description,
        public ?string $senderEmail = null,
        public ?string $page = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Tunzone feature suggestion',
            replyTo: $this->senderEmail ? [new Address($this->senderEmail)] : [],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.feature-request',
            text: 'emails.feature-request-text',
        );
    }
}
