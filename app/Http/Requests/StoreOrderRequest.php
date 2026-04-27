<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_email' => ['required', 'email'],
            'customer_phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'type' => ['required', 'in:catalog,module,custom'],
            'total_price' => ['required', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:pending,reviewed,quoted,accepted,rejected'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:catalog,module,custom'],
            'items.*.item_id' => ['sometimes', 'nullable', 'string'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.price' => ['required', 'numeric', 'min:0'],
            'items.*.selected_materials' => ['sometimes', 'array'],
            'items.*.selected_materials.*' => ['string', 'exists:materials,id'],
            'items.*.custom_data' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
