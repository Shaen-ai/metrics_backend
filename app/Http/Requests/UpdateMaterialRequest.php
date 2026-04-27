<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaterialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $hasTypes = $this->has('types') && is_array($this->types) && count($this->types) > 0;
        if (! $hasTypes && $this->filled('type')) {
            $this->merge(['types' => [$this->type]]);
        }
    }

    public function rules(): array
    {
        $typeSlugs = [
            'laminate', 'mdf', 'wood', 'worktop', 'slide', 'hinge', 'handle',
            'metal', 'fabric', 'glass', 'plastic', 'leather', 'stone',
        ];

        return [
            'mode_id' => ['sometimes', 'exists:modes,id'],
            'sub_mode_id' => ['sometimes', 'nullable', 'exists:sub_modes,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'nullable', 'string', 'max:50'],
            'types' => ['sometimes', 'array', 'min:1'],
            'types.*' => ['string', 'max:50', Rule::in($typeSlugs)],
            'categories' => ['sometimes', 'array', 'min:1'],
            'categories.*' => ['string', 'max:50'],
            'color' => ['sometimes', 'string', 'max:255'],
            'color_hex' => ['sometimes', 'nullable', 'string', 'max:7'],
            'color_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'price' => ['sometimes', 'numeric', 'min:0'],
            'price_per_unit' => ['sometimes', 'numeric', 'min:0'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'unit' => ['sometimes', 'string', 'max:20'],
            'image' => ['sometimes', 'nullable', 'string'],
            'image_url' => ['sometimes', 'nullable', 'string'],
            'sheet_width_cm' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:600'],
            'sheet_height_cm' => ['sometimes', 'nullable', 'numeric', 'min:1', 'max:600'],
            'grain_direction' => ['sometimes', 'nullable', 'in:along_width,along_height,none'],
            'kerf_mm' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:10'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
