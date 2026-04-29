<?php

namespace App\Http\Requests;

use App\Support\PlanEntitlements;
use App\Support\StorefrontSubdomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateProfileRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $texts = $this->input('public_site_texts');
        if (is_array($texts)) {
            $allowedKeys = array_flip(config('public_site.text_keys'));
            $this->merge([
                'public_site_texts' => array_intersect_key($texts, $allowedKeys),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:255'],
            'company_name' => ['sometimes', 'string', 'max:255'],
            'slug' => array_merge(['sometimes'], StorefrontSubdomain::slugRules($this->user()->id)),
            'logo' => ['sometimes', 'nullable', 'string'],
            'language' => ['sometimes', 'in:en,ru'],
            'currency' => ['sometimes', 'string', 'max:10'],
            'selected_mode_id' => ['sometimes', 'nullable', 'exists:modes,id'],
            'selected_sub_mode_ids' => ['sometimes', 'nullable', 'array'],
            'selected_sub_mode_ids.*' => ['exists:sub_modes,id'],
            'paypal_email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'planner_material_ids' => ['sometimes', 'nullable', 'array'],
            'planner_material_ids.*' => [
                'string',
                'uuid',
                Rule::exists('materials', 'id')->where('admin_id', $this->user()->id),
            ],
            'use_custom_planner_catalog' => ['sometimes', 'boolean'],
            'public_site_layout' => ['sometimes', 'string', Rule::in(config('public_site.layouts'))],
            'public_site_texts' => ['sometimes', 'nullable', 'array'],
            'public_site_texts.*' => ['nullable', 'string', 'max:500'],
            'public_site_theme' => ['sometimes', 'nullable', 'array'],
            'public_site_theme.primaryColor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'public_site_theme.accentColor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'public_site_theme.backgroundColor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'public_site_theme.textColor' => ['sometimes', 'nullable', 'string', 'max:32'],
            'custom_design_key' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $user = $this->user();
            $defaultLayout = config('public_site.default_layout');

            if ($this->filled('public_site_layout')
                && $this->input('public_site_layout') !== $defaultLayout
                && ! PlanEntitlements::allowsPublishedLayouts($user)
            ) {
                $validator->errors()->add('public_site_layout', 'Published site layouts are available on Business Pro and Enterprise plans.');
            }

            if ($this->has('public_site_theme')
                && ! empty($this->input('public_site_theme'))
                && ! PlanEntitlements::allowsCustomTheme($user)
            ) {
                $validator->errors()->add('public_site_theme', 'Custom published site themes are available on Business Pro and Enterprise plans.');
            }

            if ($this->filled('custom_design_key') && ! PlanEntitlements::allowsBespokeDesign($user)) {
                $validator->errors()->add('custom_design_key', 'Bespoke designs are available on Enterprise plans.');
            }
        });

        $validator->after(function (Validator $validator): void {
            $slug = $this->input('slug');
            if (! is_string($slug) || $slug === '') {
                return;
            }
            if (StorefrontSubdomain::isReserved($slug)) {
                $validator->errors()->add('slug', 'This subdomain is reserved.');
            }
        });
    }
}
