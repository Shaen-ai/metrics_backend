@extends('emails.layouts.branded')

@section('title', 'Verify your email')

@section('content')
<p style="margin:0 0 16px;font-size:16px;">Hi {{ \App\Support\MailBranding::greetingName($user->name) }},</p>
<p style="margin:0 0 24px;font-size:16px;">Thanks for signing up with {{ config('mail.from.name') }}. Confirm your email to finish creating your account:</p>
<p style="margin:0 0 24px;">
<a href="{{ $verificationUrl }}" style="display:inline-block;padding:12px 20px;background:#111827;color:#ffffff;text-decoration:none;border-radius:6px;font-weight:600;font-size:15px;">Verify your email address</a>
</p>
<p style="margin:0 0 8px;font-size:13px;color:#6b7280;">If the button does not work, open this link in your browser:</p>
<p style="margin:0 0 24px;font-size:13px;color:#374151;word-break:break-all;">{{ $verificationUrl }}</p>
<p style="margin:0;font-size:13px;color:#6b7280;">If you did not create an account, you can ignore this message.</p>
@endsection
