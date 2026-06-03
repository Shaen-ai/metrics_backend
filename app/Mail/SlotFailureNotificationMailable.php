<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SlotFailureNotificationMailable extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{family: string, subtype?: string|null, label: string}>  $failedSlots
     */
    public function __construct(
        public string $phase,
        public string $roomType,
        public string $style,
        public array $failedSlots,
        public ?string $designIntent = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Catalog slot failure — '.config('mail.from.name').' phased design',
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.slot-failure-notification',
            text: 'emails.slot-failure-notification-text',
        );
    }
}
