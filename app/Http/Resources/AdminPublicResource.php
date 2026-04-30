<?php

namespace App\Http\Resources;

use App\Support\PlanEntitlements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPublicResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subModeSlugs = [];
        if (!empty($this->selected_sub_mode_ids)) {
            $subModeSlugs = \App\Models\SubMode::whereIn('id', $this->selected_sub_mode_ids)
                ->pluck('slug')
                ->values()
                ->toArray();
        }

        return [
            'id' => $this->id,
            'companyName' => $this->company_name,
            'slug' => $this->slug,
            'logo' => $this->logo,
            'language' => $this->language,
            'currency' => $this->currency,
            'selectedPlannerTypes' => $subModeSlugs,
            'paypalEmail' => $this->paypal_email,
            'plannerMaterialIds' => (is_array($this->planner_material_ids) && count($this->planner_material_ids) > 0)
                ? $this->planner_material_ids
                : null,
            'useCustomPlannerCatalog' => (bool) $this->use_custom_planner_catalog,
            'publicSiteLayout' => PlanEntitlements::allowsPublishedLayouts($this->resource)
                ? ($this->public_site_layout ?? config('public_site.default_layout'))
                : config('public_site.default_layout'),
            'publicSiteTexts' => PlanEntitlements::allowsCustomTheme($this->resource)
                ? ($this->public_site_texts ?? [])
                : [],
            'publicSiteTheme' => PlanEntitlements::allowsCustomTheme($this->resource)
                ? ($this->public_site_theme ?? [])
                : [],
            'publicCatalogLayouts' => (is_array($this->public_catalog_layouts) && count($this->public_catalog_layouts) > 0)
                ? $this->public_catalog_layouts
                : config('public_site.catalog_layouts'),
            'publicCatalogDefaultLayout' => $this->public_catalog_default_layout ?? config('public_site.default_catalog_layout'),
            'customDesignKey' => PlanEntitlements::allowsBespokeDesign($this->resource)
                ? $this->custom_design_key
                : null,
            'entitlements' => PlanEntitlements::toPublicArray($this->resource),
        ];
    }
}
