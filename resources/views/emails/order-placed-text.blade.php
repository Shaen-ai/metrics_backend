New order received

Customer: {{ $order->customer_name }}
Email: {{ $order->customer_email }}
Phone: {{ $order->customer_phone ?: 'N/A' }}
@if($order->customer_address)
Delivery address:
{{ $order->customer_address }}

@endif
Total: {{ number_format($order->total_price, 2) }}
Payment: Arrange payment and delivery with the customer as you usually do.

Order items:
@foreach($order->items as $item)
- {{ $item->name }} x{{ $item->quantity }} - {{ number_format($item->price * $item->quantity, 2) }}
@endforeach

View orders: {{ rtrim(config('app.frontend_admin_url'), '/') }}/admin/orders

Contact the customer to arrange delivery and payment collection.
