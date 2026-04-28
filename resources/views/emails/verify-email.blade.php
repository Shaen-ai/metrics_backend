<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify your email</title>
</head>
<body style="margin:0;padding:24px;font-family:system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;line-height:1.5;color:#111827;background:#f9fafb;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#ffffff;border-radius:8px;padding:32px;border:1px solid #e5e7eb;">
<tr>
<td>
<p style="margin:0 0 16px;font-size:16px;">Hi {{ $user->name }},</p>
<p style="margin:0 0 24px;font-size:16px;">Thanks for signing up with {{ config('app.name') }}. Confirm your email to finish creating your account:</p>
<p style="margin:0 0 24px;">
<a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">Verify your email address</a>
</p>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">If the button does not work, open this link in your browser:</p>
<p style="margin:0 0 24px;font-size:13px;color:#374151;word-break:break-all;">{{ $verificationUrl }}</p>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not create an account, you can ignore this message.</p>
</td>
</tr>
</table>
</body>
</html>
