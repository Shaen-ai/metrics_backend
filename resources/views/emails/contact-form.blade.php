<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Contact form</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p><strong>From:</strong> {{ $senderName }} &lt;{{ $senderEmail }}&gt;</p>
    <p><strong>Message:</strong></p>
    <p style="white-space: pre-wrap;">{{ $bodyText }}</p>
</body>
</html>
