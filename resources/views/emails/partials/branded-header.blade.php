@php($logoUrl = config('mail.brand_logo_url'))
@php($brandName = config('mail.from.name'))
@if($logoUrl)
<div style="margin-bottom:20px;">
<img src="{{ $logoUrl }}" alt="{{ $brandName }}" width="140" style="max-width:140px;height:auto;display:block;border:0;" />
</div>
@else
<p style="margin:0 0 16px;font-size:18px;font-weight:700;color:#111827;">{{ $brandName }}</p>
@endif
