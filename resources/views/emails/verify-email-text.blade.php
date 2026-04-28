Hi {{ $user->name }},

Thanks for signing up with {{ config('app.name') }}. Open this link to verify your email, then sign in:

{{ $verificationUrl }}

If you did not create an account, you can ignore this message.
