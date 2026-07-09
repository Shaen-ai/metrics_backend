Vista AI generation issue

Category: {{ $category }}
@if ($provider)
Provider: {{ $provider }}
@endif
Route: {{ $route }}
@if ($roomType)
Room type: {{ $roomType }}
@endif
@if ($phase)
Phase: {{ $phase }}
@endif
@if ($occurredAt)
Occurred at: {{ $occurredAt }}
@endif

Error message:
{{ $errorMessage }}

The user was shown a friendly message; provider auth/config failures also trigger a contact-support popup.
