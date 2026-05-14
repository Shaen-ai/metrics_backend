<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Planner inquiry</title>
</head>
<body style="font-family: system-ui, sans-serif; line-height: 1.5; color: #111;">
    <p><strong>Store:</strong> {{ $admin->company_name }}</p>
    <p><strong>Planner:</strong> {{ $plannerLabel }} <span style="color:#555;">({{ $plannerType }})</span></p>
    <p><strong>From:</strong> {{ $customerName }} &lt;{{ $customerEmail }}&gt;</p>
    @if(!empty($notes))
        <p><strong>Customer notes:</strong></p>
        <p style="white-space: pre-wrap;">{{ $notes }}</p>
    @endif

    @if($previewAttached)
        <p style="margin:12px 0;"><strong>Attachment:</strong> A generated interior preview image is included with this message.</p>
    @endif

    @if(!empty($designSummary['interior_brief']))
        <h2 style="font-size:18px;margin:24px 0 8px;">AI design brief</h2>
        <table cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin:0 0 18px;width:100%;max-width:680px;">
            @foreach(($designSummary['interior_brief'] ?? []) as $row)
                <tr>
                    <td style="padding:6px 10px;border:1px solid #e2e4e8;background:#f8fafc;width:170px;vertical-align:top;"><strong>{{ $row['label'] }}</strong></td>
                    <td style="padding:6px 10px;border:1px solid #e2e4e8;white-space:pre-wrap;">{{ $row['value'] }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    @if(!empty($designSummary['interior_chat']))
        <h3 style="font-size:16px;margin:18px 0 8px;">Design chat (recent)</h3>
        @foreach(($designSummary['interior_chat'] ?? []) as $line)
            <p style="margin:0 0 8px;padding:8px 10px;background:#f8fafc;border:1px solid #e2e4e8;border-radius:6px;">
                <strong>{{ $line['role'] }}:</strong>
                <span style="white-space:pre-wrap;">{{ $line['content'] }}</span>
            </p>
        @endforeach
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

    <h3 style="font-size:16px;margin:18px 0 8px;">
        @if(($design['variant'] ?? null) === 'interior-design')
            Catalog items (from your store)
        @else
            Products requested
        @endif
    </h3>
    @forelse(($designSummary['products'] ?? []) as $product)
        <div style="border:1px solid #e2e4e8;border-radius:8px;padding:12px;margin:0 0 12px;">
            <p style="margin:0 0 8px;"><strong>{{ $product['title'] }}</strong></p>
            @if(!empty($product['details']))
                <ul style="margin:0;padding-left:20px;">
                    @foreach($product['details'] as $detail)
                        <li><strong>{{ $detail['label'] }}:</strong> {{ $detail['value'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @empty
        @if(($design['variant'] ?? null) === 'interior-design')
            <p>No catalog SKUs from your database were linked to this design. See the AI brief and JSON for descriptive materials and finishes.</p>
        @else
            <p>No products were placed in the planner.</p>
        @endif
    @endforelse

    @if(!empty($designSummary['materials']))
        <h3 style="font-size:16px;margin:18px 0 8px;">Materials used</h3>
        <ul style="margin-top:0;padding-left:20px;">
            @foreach($designSummary['materials'] as $material)
                <li>{{ $material['name'] }} <span style="color:#555;">({{ $material['id'] }})</span> - used {{ $material['count'] }} time(s)</li>
            @endforeach
        </ul>
    @endif

    <details style="margin-top:24px;">
        <summary style="cursor:pointer;color:#555;font-size:13px;">Technical design data (JSON backup)</summary>
        <pre style="font-size:11px;line-height:1.35;overflow:auto;max-height:320px;background:#f6f7f9;padding:12px;border-radius:8px;border:1px solid #e2e4e8;margin-top:8px;">{{ e($designJsonPretty) }}</pre>
    </details>
</body>
</html>
