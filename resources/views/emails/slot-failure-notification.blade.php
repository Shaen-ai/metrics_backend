<p>A phased interior design request could not match catalog products for one or more slots.</p>

<ul>
    <li><strong>Phase:</strong> {{ $phase }}</li>
    <li><strong>Room type:</strong> {{ $roomType }}</li>
    <li><strong>Style:</strong> {{ $style }}</li>
</ul>

@if ($designIntent)
    <p><strong>Design intent:</strong> {{ $designIntent }}</p>
@endif

<p><strong>Unresolved slots:</strong></p>
<ul>
    @foreach ($failedSlots as $slot)
        <li>{{ $slot['label'] }} ({{ $slot['family'] }}{{ !empty($slot['subtype']) ? ' / '.$slot['subtype'] : '' }})</li>
    @endforeach
</ul>

<p>The user was shown an AI-suggested fallback for these elements.</p>
