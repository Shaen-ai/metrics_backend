<?php

namespace App\Http\Controllers;

use App\Models\StorefrontFloorPlan;
use App\Models\User;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StorefrontFloorPlanController extends Controller
{
    use Concerns\DetectsUploadFailures;
    /**
     * POST /api/public/{slug}/floor-plans
     *
     * Upload a floor plan image for the given merchant storefront.
     */
    public function store(Request $request, string $slug): JsonResponse
    {
        $admin = $this->resolveSubscribedAdmin($slug);
        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $file = $request->file('image');
        $invalid = $this->invalidUploadResponse('image', $file);
        if ($invalid !== null) {
            return $invalid;
        }

        $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:10240'],
        ]);

        $file = $request->file('image');
        $ext = strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');
        $filename = uniqid('fp_', true).'.'.$ext;
        $path = $file->storeAs(
            'floor-plans/'.$admin->id,
            $filename,
            'public'
        );

        $plan = StorefrontFloorPlan::create([
            'admin_id' => $admin->id,
            'floor_plan_path' => $path,
            'status' => 'pending',
        ]);

        return response()->json([
            'data' => $this->planResource($plan, $admin->slug),
        ], 201);
    }

    /**
     * PATCH /api/public/{slug}/floor-plans/{id}
     *
     * Update (confirm) the floor plan analysis JSON.
     */
    public function update(Request $request, string $slug, string $id): JsonResponse
    {
        $admin = $this->resolveSubscribedAdmin($slug);
        if ($admin instanceof JsonResponse) {
            return $admin;
        }

        $plan = StorefrontFloorPlan::where('id', $id)
            ->where('admin_id', $admin->id)
            ->firstOrFail();

        $data = $request->validate([
            'floor_plan_analysis' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'max:24'],
        ]);

        if (array_key_exists('floor_plan_analysis', $data)) {
            $plan->floor_plan_analysis = $data['floor_plan_analysis'];
        }
        if (! empty($data['status'])) {
            $plan->status = $data['status'];
        }
        $plan->save();

        return response()->json([
            'data' => $this->planResource($plan, $admin->slug),
        ]);
    }

    /**
     * GET /api/public/{slug}/floor-plans/{id}
     *
     * Retrieve a saved floor plan with image URL and analysis.
     */
    public function show(Request $request, string $slug, string $id): JsonResponse
    {
        $admin = User::where('slug', $slug)->first();
        if (! $admin) {
            return response()->json(['message' => 'Storefront not found.'], 404);
        }

        $plan = StorefrontFloorPlan::where('id', $id)
            ->where('admin_id', $admin->id)
            ->firstOrFail();

        return response()->json([
            'data' => $this->planResource($plan, $admin->slug),
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function resolveSubscribedAdmin(string $slug): User|JsonResponse
    {
        $admin = User::where('slug', $slug)->first();
        if (! $admin) {
            return response()->json(['message' => 'Storefront not found.'], 404);
        }
        if (! PlanEntitlements::hasActiveSubscription($admin)) {
            return response()->json(
                ['message' => 'This workspace does not have an active subscription.'],
                403
            );
        }

        return $admin;
    }

    private function planResource(StorefrontFloorPlan $plan, string $slug): array
    {
        $imageUrl = $plan->floor_plan_path
            ? url(Storage::disk('public')->url($plan->floor_plan_path))
            : null;

        return [
            'id' => $plan->id,
            'admin_id' => $plan->admin_id,
            'slug' => $slug,
            'floor_plan_url' => $imageUrl,
            'floor_plan_analysis' => $plan->floor_plan_analysis,
            'status' => $plan->status,
            'created_at' => $plan->created_at,
            'updated_at' => $plan->updated_at,
        ];
    }

}
