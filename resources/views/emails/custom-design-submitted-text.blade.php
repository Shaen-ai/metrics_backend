Custom design submitted

Store: {{ $customDesign->admin?->company_name }}
Room: {{ $customDesign->room_name ?: 'Untitled room' }}
Status: {{ $customDesign->status }}
@if($customDesign->customer_name || $customDesign->customer_email)
Customer: {{ $customDesign->customer_name ?: 'Customer' }} @if($customDesign->customer_email)<{{ $customDesign->customer_email }}>@endif
@endif
@if($customDesign->notes)

Notes:
{{ $customDesign->notes }}
@endif
@if($customDesign->snapshot_path)

Snapshot: {{ $customDesign->snapshot_path }}
@endif

--- Design summary ---
@foreach(($designSummary['overview'] ?? []) as $row)
{{ $row['label'] }}: {{ $row['value'] }}
@endforeach
@if(!empty($designSummary['layers']))

Layers:
@foreach($designSummary['layers'] as $layer)
  - {{ $layer }}
@endforeach
@endif
@if($designSummary['elementCount'] > 0)

Elements on canvas: {{ $designSummary['elementCount'] }}
@foreach(($designSummary['elementTypes'] ?? []) as $type => $count)
  - {{ ucfirst($type) }}: {{ $count }}
@endforeach
@endif

--- Technical design data (JSON backup) ---
{{ $designJsonPretty }}
