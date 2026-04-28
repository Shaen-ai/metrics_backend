<?php

namespace App\Notifications;

use App\Mail\OrderPaidMailable;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class OrderPaidNotification extends Notification
{
    use Queueable;

    public function __construct(
        private Order $order
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): OrderPaidMailable
    {
        Log::info('Sending OrderPaidNotification email', [
            'to' => $notifiable->email,
            'order_id' => $this->order->id,
            'customer' => $this->order->customer_name,
            'total' => $this->order->total_price,
        ]);

        return new OrderPaidMailable($this->order);
    }
}
