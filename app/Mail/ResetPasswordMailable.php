<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public string $resetUrl
    ) {}

    public function envelope(): Envelope
    {
        $replyTo = [];
        $replyAddress = config('mail.reply_to.address');
        if (is_string($replyAddress) && $replyAddress !== '') {
            $replyName = config('mail.reply_to.name');
            $replyTo[] = new Address($replyAddress, is_string($replyName) && $replyName !== '' ? $replyName : null);
        }

        return new Envelope(
            subject: 'Reset your password — '.config('mail.from.name'),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.reset-password',
            text: 'emails.reset-password-text',
        );
    }
}
