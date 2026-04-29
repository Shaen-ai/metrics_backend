<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ContactFormMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $bodyText,
    ) {}

    public function envelope(): Envelope
    {
        $safeName = Str::limit(preg_replace('/[\r\n]+/', ' ', $this->senderName) ?? '', 100, '');

        return new Envelope(
            subject: 'Website contact: '.$safeName.' — '.config('mail.from.name'),
            replyTo: [
                new Address($this->senderEmail, Str::limit($this->senderName, 70, '') ?: null),
            ],
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact-form',
            text: 'emails.contact-form-text',
        );
    }
}
