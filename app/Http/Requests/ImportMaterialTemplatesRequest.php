<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportMaterialTemplatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'template_ids' => ['required', 'array', 'min:1', 'max:100'],
            'template_ids.*' => ['required', 'uuid', 'distinct', 'exists:material_templates,id'],
            'mode_id' => ['required', 'exists:modes,id'],
            'sub_mode_id' => ['sometimes', 'nullable', 'exists:sub_modes,id'],
            'categories' => ['sometimes', 'array', 'min:1'],
            'categories.*' => ['string', 'max:50'],
            'price_per_unit' => ['required', 'numeric', 'min:0'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'unit' => ['sometimes', 'string', 'max:20'],
        ];
    }
}
