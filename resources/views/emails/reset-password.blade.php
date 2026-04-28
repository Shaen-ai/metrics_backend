@extends('emails.layouts.branded')

@section('title', 'Reset your password')

@section('content')
<p style="margin:0 0 16px;font-size:16px;">Hi {{ \App\Support\MailBranding::greetingName($user->name) }},</p>
<p style="margin:0 0 24px;font-size:16px;">We received a request to reset your password for your {{ config('mail.from.name') }} account. Choose a new password using the link below:</p>
<p style="margin:0 0 24px;">
<a href="{{ $resetUrl }}" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">Reset password</a>
</p>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">If the button does not work, copy this link into your browser:</p>
<p style="margin:0 0 24px;font-size:13px;color:#374151;word-break:break-all;">{{ $resetUrl }}</p>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not request a password reset, you can ignore this email. Your password will stay the same.</p>
@endsection
