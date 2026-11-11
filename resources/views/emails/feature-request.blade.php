<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>Feature suggestion</title></head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <h1>New feature suggestion</h1>
    <p><strong>Reply email:</strong> {{ $senderEmail ?: 'Not provided' }}</p>
    @if ($page)
    <p><strong>Page:</strong> {{ $page }}</p>
    @endif
    <p><strong>Suggestion:</strong></p>
    <p style="white-space: pre-wrap;">{{ $description }}</p>
</body>
</html>
