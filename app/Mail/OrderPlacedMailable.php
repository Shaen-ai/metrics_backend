<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order
    ) {
        $this->order->load('items');
    }

    public function envelope(): Envelope
    {
        $replyTo = [];
        $replyAddress = config('mail.reply_to.address');
        if (is_string($replyAddress) && $replyAddress !== '') {
            $replyName = config('mail.reply_to.name');
            $replyTo[] = new Address($replyAddress, is_string($replyName) && $replyName !== '' ? $replyName : null);
        }

        $idShort = substr($this->order->id, 0, 8);

        return new Envelope(
            subject: 'New order #'.$idShort.' - payment due on shipment - '.config('mail.from.name'),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.order-placed',
            text: 'emails.order-placed-text',
        );
    }
}
