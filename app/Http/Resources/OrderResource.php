<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'adminId' => $this->admin_id,
            'customerName' => $this->customer_name,
            'customerEmail' => $this->customer_email,
            'customerPhone' => $this->customer_phone,
            'customerAddress' => $this->customer_address,
            'type' => $this->type,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'totalPrice' => (float) $this->total_price,
            'status' => $this->status,
            'paymentStatus' => $this->payment_status,
            'paypalTransactionId' => $this->paypal_transaction_id,
            'notes' => $this->notes,
            'createdAt' => $this->created_at?->toISOString(),
        ];
    }
}
