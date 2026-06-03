Phased interior design — catalog slot failure

Phase: {{ $phase }}
Room type: {{ $roomType }}
Style: {{ $style }}
@if ($designIntent)
Design intent: {{ $designIntent }}
@endif

Unresolved slots:
@foreach ($failedSlots as $slot)
- {{ $slot['label'] }} ({{ $slot['family'] }}{{ !empty($slot['subtype']) ? ' / '.$slot['subtype'] : '' }})
@endforeach

The user was shown an AI-suggested fallback for these elements.
