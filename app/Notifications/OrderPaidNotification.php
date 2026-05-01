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
        return $this->adminMailAddress($notifiable) !== null ? ['mail'] : [];
    }

    public function toMail(object $notifiable): OrderPaidMailable
    {
        $to = $this->adminMailAddress($notifiable);
        if ($to === null) {
            throw new \LogicException('OrderPaidNotification: mail channel requires a valid admin email.');
        }

        Log::info('Sending OrderPaidNotification email', [
            'to' => $to,
            'order_id' => $this->order->id,
            'customer' => $this->order->customer_name,
            'total' => $this->order->total_price,
        ]);

        return (new OrderPaidMailable($this->order))->to($to);
    }

    private function adminMailAddress(object $notifiable): ?string
    {
        $route = $notifiable->routeNotificationFor('mail', $this);
        if (! is_string($route)) {
            Log::warning('OrderPaidNotification skipped: mail route not a string', [
                'admin_id' => $notifiable->getKey() ?? null,
            ]);

            return null;
        }

        $email = trim($route);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('OrderPaidNotification skipped: admin has no valid email', [
                'admin_id' => $notifiable->getKey() ?? null,
            ]);

            return null;
        }

        return $email;
    }
}
