Hi {{ \App\Support\MailBranding::greetingName($user->name) }},

Thanks for signing up with {{ config('mail.from.name') }}. Open this link to verify your email, then sign in:

{{ $verificationUrl }}

If you did not create an account, you can ignore this message.

—
{{ config('mail.from.name') }}
