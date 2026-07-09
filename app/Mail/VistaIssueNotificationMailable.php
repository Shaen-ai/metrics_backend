<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VistaIssueNotificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $category,
        public ?string $provider,
        public string $route,
        public string $errorMessage,
        public ?string $roomType = null,
        public ?string $phase = null,
        public ?string $occurredAt = null,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->category === 'provider_auth'
            ? 'Provider auth/config failure'
            : 'Unexpected generation error';

        return new Envelope(
            subject: 'Vista '.$label.' — '.config('mail.from.name'),
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.vista-issue-notification',
            text: 'emails.vista-issue-notification-text',
        );
    }
}
