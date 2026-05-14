<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCustomDesignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'design' => ['required', 'array'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $encoded = json_encode($this->input('design'));
            if ($encoded === false || strlen($encoded) > 900000) {
                $validator->errors()->add('design', 'Design data is too large to save.');
            }
        });
    }
}
