<?php

namespace App\Http\Resources;

use App\Support\PlanEntitlements;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'name' => $this->name,
            'companyName' => $this->company_name,
            'slug' => $this->slug,
            'sitePublishedAt' => $this->site_published_at?->toISOString(),
            'selectedModeId' => $this->selected_mode_id,
            'selectedSubModeIds' => $this->selected_sub_mode_ids ?? [],
            'logo' => $this->logo,
            'language' => $this->language,
            'currency' => $this->currency,
            'paypalEmail' => $this->paypal_email,
            'plannerMaterialIds' => (is_array($this->planner_material_ids) && count($this->planner_material_ids) > 0)
                ? $this->planner_material_ids
                : null,
            'useCustomPlannerCatalog' => (bool) $this->use_custom_planner_catalog,
            'publicSiteLayout' => $this->public_site_layout ?? config('public_site.default_layout'),
            'publicSiteTexts' => $this->public_site_texts ?? [],
            'publicSiteTheme' => $this->public_site_theme ?? [],
            'publicCatalogLayouts' => (is_array($this->public_catalog_layouts) && count($this->public_catalog_layouts) > 0)
                ? $this->public_catalog_layouts
                : config('public_site.catalog_layouts'),
            'publicCatalogDefaultLayout' => $this->public_catalog_default_layout ?? config('public_site.default_catalog_layout'),
            'customDesignKey' => $this->custom_design_key,
            'createdAt' => $this->created_at?->toISOString(),
            'planTier' => PlanEntitlements::normalizePlanTier($this->plan_tier ?? 'free'),
            'trialEndsAt' => $this->trial_ends_at?->toISOString(),
            'entitlements' => PlanEntitlements::toPublicArray($this->resource),
        ];
    }
}
