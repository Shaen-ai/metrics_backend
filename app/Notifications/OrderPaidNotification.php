<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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

    public function toMail(object $notifiable): MailMessage
    {
        $order = $this->order->load('items');
        $itemLines = $order->items->map(
            fn ($item) => "{$item->name} x{$item->quantity} — " . number_format($item->price * $item->quantity, 2)
        )->toArray();

        Log::info('Sending OrderPaidNotification email', [
            'to' => $notifiable->email,
            'order_id' => $order->id,
            'customer' => $order->customer_name,
            'total' => $order->total_price,
        ]);

        $mail = (new MailMessage)
            ->subject('New Paid Order #' . substr($order->id, 0, 8))
            ->greeting('New order received!')
            ->line("**Customer:** {$order->customer_name}")
            ->line("**Email:** {$order->customer_email}")
            ->line("**Phone:** " . ($order->customer_phone ?: 'N/A'))
            ->line("**Total:** " . number_format($order->total_price, 2))
            ->line("**Payment:** Paid via PayPal (TXN: {$order->paypal_transaction_id})")
            ->line('')
            ->line('**Order Items:**');

        foreach ($itemLines as $line) {
            $mail->line("- {$line}");
        }

        $adminUrl = config('app.frontend_admin_url', 'http://localhost:3000');
        $mail->action('View Order in Dashboard', "{$adminUrl}/admin/orders")
            ->line('Please process this order promptly.');

        return $mail;
    }
}
