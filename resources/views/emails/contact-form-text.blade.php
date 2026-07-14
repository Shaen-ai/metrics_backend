From: {{ $senderName }} <{{ $senderEmail }}>
@if (!empty($phone))
Phone: {{ $phone }}
@endif
@if (!empty($source))
Source: {{ $source }}
@endif

Message:
{{ $bodyText }}
