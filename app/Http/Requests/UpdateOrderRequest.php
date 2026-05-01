<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'in:pending,confirmed,reviewed,quoted,accepted,rejected,delivered'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
