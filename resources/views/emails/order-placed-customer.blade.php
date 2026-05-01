@extends('emails.layouts.branded')

@section('title', 'Order received')

@section('content')
<p style="margin:0 0 16px;font-size:16px;font-weight:600;">Thank you for your order</p>
<p style="margin:0 0 16px;font-size:15px;">{{ $storeLabel }} has received your order. They will contact you soon about delivery and payment.</p>
<p style="margin:0 0 16px;font-size:15px;">You can write to <strong>{{ $storeLabel }}</strong> by replying to this email.</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Order reference:</strong> {{ substr($order->id, 0, 8) }}…</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Name:</strong> {{ $order->customer_name }}</p>
@if($order->customer_address)
<p style="margin:0 0 8px;font-size:15px;"><strong>Delivery address:</strong><br>{{ nl2br(e($order->customer_address)) }}</p>
@endif
<p style="margin:0 0 8px;font-size:15px;"><strong>Total:</strong> {{ number_format($order->total_price, 2) }}</p>
<p style="margin:0 0 12px;font-size:15px;font-weight:600;">Your items</p>
<ul style="margin:0 0 24px;padding-left:20px;font-size:14px;">
@foreach($order->items as $item)
<li style="margin-bottom:6px;">{{ $item->name }} ×{{ $item->quantity }} — {{ number_format($item->price * $item->quantity, 2) }}</li>
@endforeach
</ul>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not place this order, you can ignore this email or contact {{ $storeLabel }}.</p>
@endsection
