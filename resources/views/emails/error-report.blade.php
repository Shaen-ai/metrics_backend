<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <title>Error Report</title>
    <style>
        body { font-family: system-ui, sans-serif; line-height: 1.6; color: #111; background: #f9f9f9; margin: 0; padding: 0; }
        .container { max-width: 640px; margin: 32px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 12px rgba(0,0,0,.08); }
        .header { background: #dc2626; color: #fff; padding: 24px 32px; }
        .header h1 { margin: 0; font-size: 20px; font-weight: 700; }
        .header p { margin: 6px 0 0; opacity: .85; font-size: 14px; }
        .body { padding: 28px 32px; }
        .field { margin-bottom: 20px; }
        .field label { display: block; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #888; margin-bottom: 4px; }
        .field .value { background: #f3f4f6; border-radius: 8px; padding: 12px 14px; font-size: 14px; white-space: pre-wrap; word-break: break-word; }
        .field .value.error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
        .screenshot { margin-top: 24px; }
        .screenshot img { max-width: 100%; border-radius: 8px; border: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>⚠ Error Report</h1>
            <p>An error was reported by a site owner via the admin panel.</p>
        </div>
        <div class="body">
            <div class="field">
                <label>User ID</label>
                <div class="value">{{ $userId }}</div>
            </div>
            <div class="field">
                <label>User Email</label>
                <div class="value">{{ $userEmail }}</div>
            </div>
            <div class="field">
                <label>Error Message</label>
                <div class="value error">{{ $errorMessage }}</div>
            </div>
            @if($url)
            <div class="field">
                <label>Page URL</label>
                <div class="value">{{ $url }}</div>
            </div>
            @endif
            @if($userAgent)
            <div class="field">
                <label>User Agent</label>
                <div class="value">{{ $userAgent }}</div>
            </div>
            @endif
            @if($screenshot)
            <div class="screenshot">
                <div class="field"><label>Screenshot</label></div>
                <img src="{{ $screenshot }}" alt="Screenshot">
            </div>
            @endif
        </div>
    </div>
</body>
</html>
