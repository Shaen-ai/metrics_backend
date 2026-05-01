Thank you for your order

{{ $storeLabel }} has received your order. They will contact you soon about delivery and payment.

You can write to {{ $storeLabel }} by replying to this email.

Order reference: {{ substr($order->id, 0, 8) }}…
Name: {{ $order->customer_name }}
@if($order->customer_address)
Delivery address:
{{ $order->customer_address }}

@endif
Total: {{ number_format($order->total_price, 2) }}

Your items:
@foreach($order->items as $item)
- {{ $item->name }} ×{{ $item->quantity }} — {{ number_format($item->price * $item->quantity, 2) }}
@endforeach

If you did not place this order, you can ignore this email or contact {{ $storeLabel }}.
