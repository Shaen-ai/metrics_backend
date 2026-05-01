<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderPlacedCustomerMailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public User $admin,
    ) {
        $this->order->load('items');
    }

    public function envelope(): Envelope
    {
        $replyTo = [];
        $adminEmail = trim((string) $this->admin->email);
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $replyTo[] = new Address($adminEmail, $this->storeLabel());
        }

        $idShort = substr($this->order->id, 0, 8);

        return new Envelope(
            subject: 'Order received #'.$idShort.' — '.$this->storeLabel(),
            replyTo: $replyTo,
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.order-placed-customer',
            text: 'emails.order-placed-customer-text',
            with: [
                'storeLabel' => $this->storeLabel(),
            ],
        );
    }

    private function storeLabel(): string
    {
        $company = trim((string) $this->admin->company_name);
        if ($company !== '') {
            return $company;
        }

        $name = trim((string) $this->admin->name);

        return $name !== '' ? $name : 'Store';
    }
}
