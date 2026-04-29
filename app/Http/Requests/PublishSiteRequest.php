<?php

namespace App\Http\Requests;

use App\Support\StorefrontSubdomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PublishSiteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->user()?->id ?? '';

        return [
            'slug' => array_merge(['sometimes'], StorefrontSubdomain::slugRules($userId)),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $slug = $this->input('slug');
            if (! is_string($slug) || $slug === '') {
                return;
            }
            if (StorefrontSubdomain::isReserved($slug)) {
                $v->errors()->add('slug', 'This subdomain is reserved.');
            }
        });
    }
}
