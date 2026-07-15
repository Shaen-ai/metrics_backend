From: {{ $senderName }} <{{ $senderEmail }}>
@if (!empty($phone))
Phone: {{ $phone }}
@endif
@if (!empty($source))
Source: {{ $source }}
@endif
@if (!empty($shareUrl))
Share URL: {{ $shareUrl }}
@endif

Message:
{{ $bodyText }}
