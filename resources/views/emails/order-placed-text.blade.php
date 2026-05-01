New order received

Customer: {{ $order->customer_name }}
Email: {{ $order->customer_email }}
Phone: {{ $order->customer_phone ?: 'N/A' }}
Total: {{ number_format($order->total_price, 2) }}
Payment: Pending - collect payment when the order ships.

Order items:
@foreach($order->items as $item)
- {{ $item->name }} x{{ $item->quantity }} - {{ number_format($item->price * $item->quantity, 2) }}
@endforeach

View orders: {{ rtrim(config('app.frontend_admin_url'), '/') }}/admin/orders

Contact the customer to arrange delivery and payment collection.
