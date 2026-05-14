<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Custom design submitted</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p><strong>Store:</strong> {{ $customDesign->admin?->company_name }}</p>
    <p><strong>Room:</strong> {{ $customDesign->room_name ?: 'Untitled room' }}</p>
    <p><strong>Status:</strong> {{ $customDesign->status }}</p>
    @if($customDesign->customer_name || $customDesign->customer_email)
        <p><strong>Customer:</strong> {{ $customDesign->customer_name ?: 'Customer' }} @if($customDesign->customer_email)&lt;{{ $customDesign->customer_email }}&gt;@endif</p>
    @endif
    @if($customDesign->notes)
        <p><strong>Notes:</strong></p>
        <p style="white-space: pre-wrap;">{{ $customDesign->notes }}</p>
    @endif
    @if($customDesign->snapshot_path)
        <p><strong>Snapshot:</strong> {{ $customDesign->snapshot_path }}</p>
    @endif

    <h2 style="font-size:18px;margin:24px 0 8px;">Design summary</h2>
    <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;width:100%;max-width:680px;">
        @foreach(($designSummary['overview'] ?? []) as $row)
            <tr>
                <td style="padding:6px 10px;border:1px solid #e2e4e8;background:#f8fafc;width:170px;"><strong>{{ $row['label'] }}</strong></td>
                <td style="padding:6px 10px;border:1px solid #e2e4e8;">{{ $row['value'] }}</td>
            </tr>
        @endforeach
    </table>

    @if(!empty($designSummary['layers']))
        <h3 style="font-size:16px;margin:18px 0 8px;">Layers</h3>
        <ul style="margin-top:0;padding-left:20px;">
            @foreach($designSummary['layers'] as $layer)
                <li>{{ $layer }}</li>
            @endforeach
        </ul>
    @endif

    @if($designSummary['elementCount'] > 0)
        <h3 style="font-size:16px;margin:18px 0 8px;">Elements on canvas</h3>
        <p style="margin:0 0 4px;"><strong>Total:</strong> {{ $designSummary['elementCount'] }}</p>
        @if(!empty($designSummary['elementTypes']))
            <ul style="margin-top:4px;padding-left:20px;">
                @foreach($designSummary['elementTypes'] as $type => $count)
                    <li>{{ ucfirst($type) }}: {{ $count }}</li>
                @endforeach
            </ul>
        @endif
    @elseif(!empty($designSummary['layers']))
        <p style="color:#555;">No elements drawn on canvas.</p>
    @endif

    <details style="margin-top:24px;">
        <summary style="cursor:pointer;color:#555;font-size:13px;">Technical design data (JSON backup)</summary>
        <pre style="font-size:11px;line-height:1.35;overflow:auto;max-height:320px;background:#f6f7f9;padding:12px;border-radius:8px;border:1px solid #e2e4e8;margin-top:8px;">{{ e($designJsonPretty) }}</pre>
    </details>
</body>
</html>
