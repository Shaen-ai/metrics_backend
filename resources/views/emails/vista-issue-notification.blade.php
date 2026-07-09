<p>A Vista AI generation issue was reported from the server.</p>

<ul>
    <li><strong>Category:</strong> {{ $category }}</li>
    @if ($provider)
        <li><strong>Provider:</strong> {{ $provider }}</li>
    @endif
    <li><strong>Route:</strong> {{ $route }}</li>
    @if ($roomType)
        <li><strong>Room type:</strong> {{ $roomType }}</li>
    @endif
    @if ($phase)
        <li><strong>Phase:</strong> {{ $phase }}</li>
    @endif
    @if ($occurredAt)
        <li><strong>Occurred at:</strong> {{ $occurredAt }}</li>
    @endif
</ul>

<p><strong>Error message:</strong></p>
<pre style="white-space: pre-wrap; word-break: break-word;">{{ $errorMessage }}</pre>

<p>The user was shown a friendly message; provider auth/config failures also trigger a contact-support popup.</p>
