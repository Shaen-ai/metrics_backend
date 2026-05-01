@extends('emails.layouts.branded')

@section('title', 'New order')

@section('content')
<p style="margin:0 0 16px;font-size:16px;font-weight:600;">New order received</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Customer:</strong> {{ $order->customer_name }}</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Email:</strong> {{ $order->customer_email }}</p>
<p style="margin:0 0 8px;font-size:15px;"><strong>Phone:</strong> {{ $order->customer_phone ?: 'N/A' }}</p>
@if($order->customer_address)
<p style="margin:0 0 8px;font-size:15px;"><strong>Delivery address:</strong><br>{{ nl2br(e($order->customer_address)) }}</p>
@endif
<p style="margin:0 0 8px;font-size:15px;"><strong>Total:</strong> {{ number_format($order->total_price, 2) }}</p>
<p style="margin:0 0 24px;font-size:15px;"><strong>Payment:</strong> Arrange payment and delivery with the customer as you usually do.</p>
<p style="margin:0 0 12px;font-size:15px;font-weight:600;">Order items</p>
<ul style="margin:0 0 24px;padding-left:20px;font-size:14px;">
@foreach($order->items as $item)
<li style="margin-bottom:6px;">{{ $item->name }} x{{ $item->quantity }} - {{ number_format($item->price * $item->quantity, 2) }}</li>
@endforeach
</ul>
<p style="margin:0 0 16px;">
<a href="{{ rtrim(config('app.frontend_admin_url'), '/') }}/admin/orders" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">View orders in dashboard</a>
</p>
<p style="margin:0;font-size:13px;color:#6b7280;">Contact the customer to arrange delivery and payment collection.</p>
@endsection
