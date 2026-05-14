<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ErrorReportMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userId,
        public string $userEmail,
        public string $errorMessage,
        public ?string $screenshot,
        public ?string $url,
        public ?string $userAgent,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '[Error Report] '.config('mail.from.name').' — user '.$this->userId,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.error-report',
            text: 'emails.error-report-text',
        );
    }
}
