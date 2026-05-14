Store: {{ $admin->company_name }}
Planner: {{ $plannerLabel }} ({{ $plannerType }})
From: {{ $customerName }} <{{ $customerEmail }}>

@if(!empty($notes))
Customer notes:
{{ $notes }}

@endif
@if($previewAttached)
Attachment: A generated interior preview image is included with this message.

@endif
@if(!empty($designSummary['interior_brief']))
AI design brief:
@foreach(($designSummary['interior_brief'] ?? []) as $row)
{{ $row['label'] }}:
{{ $row['value'] }}

@endforeach
@endif

@if(!empty($designSummary['interior_chat']))
Design chat (recent):
@foreach(($designSummary['interior_chat'] ?? []) as $line)
{{ $line['role'] }}: {{ $line['content'] }}

@endforeach
@endif

Design summary:
@foreach(($designSummary['overview'] ?? []) as $row)
{{ $row['label'] }}: {{ $row['value'] }}
@endforeach

@if(($design['variant'] ?? null) === 'interior-design')
Catalog items (from your store):
@else
Products requested:
@endif
@forelse(($designSummary['products'] ?? []) as $product)
{{ $product['title'] }}
@foreach(($product['details'] ?? []) as $detail)
- {{ $detail['label'] }}: {{ $detail['value'] }}
@endforeach

@empty
@if(($design['variant'] ?? null) === 'interior-design')
No catalog SKUs from your database were linked to this design. See the AI brief and JSON for descriptive materials and finishes.
@else
No products were placed in the planner.
@endif
@endforelse
@if(!empty($designSummary['materials']))
Materials used:
@foreach($designSummary['materials'] as $material)
- {{ $material['name'] }} ({{ $material['id'] }}) - used {{ $material['count'] }} time(s)
@endforeach

@endif
Technical design data (JSON backup):
{{ $designJsonPretty }}
