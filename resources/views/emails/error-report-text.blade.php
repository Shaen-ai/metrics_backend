ERROR REPORT — {{ config('mail.from.name') }}
==============================================

User ID:    {{ $userId }}
User Email: {{ $userEmail }}

Error:
{{ $errorMessage }}

@if($url)
Page: {{ $url }}
@endif
@if($userAgent)
Browser: {{ $userAgent }}
@endif
