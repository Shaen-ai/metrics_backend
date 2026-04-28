Hi {{ \App\Support\MailBranding::greetingName($user->name) }},

We received a request to reset your password for your {{ config('mail.from.name') }} account.

Open this link to choose a new password:

{{ $resetUrl }}

If you did not request a password reset, you can ignore this email.

—
{{ config('mail.from.name') }}
