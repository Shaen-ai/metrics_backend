New order received

Customer: {{ $order->customer_name }}
Email: {{ $order->customer_email }}
Phone: {{ $order->customer_phone ?: 'N/A' }}
Total: {{ number_format($order->total_price, 2) }}
Payment: Paid via PayPal (TXN: {{ $order->paypal_transaction_id }})

Order items:
@foreach($order->items as $item)
- {{ $item->name }} ×{{ $item->quantity }} — {{ number_format($item->price * $item->quantity, 2) }}
@endforeach

View orders: {{ rtrim(config('app.frontend_admin_url'), '/') }}/admin/orders

Please process this order promptly.

—
{{ config('mail.from.name') }}
