<?php

namespace App\Http\Controllers;

use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageConsumeController extends Controller
{
    public function consume(Request $request): JsonResponse
    {
        $data = $request->validate([
            'feature' => 'required|string|in:image3d,ai_chat',
            'consume' => 'sometimes|boolean',
        ]);
        $user = $request->user();
        $consume = (bool) ($data['consume'] ?? true);

        if (! PlanEntitlements::hasActiveSubscription($user)) {
            return response()->json([
                'message' => 'An active subscription is required.',
                'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
            ], 403);
        }

        if ($data['feature'] === 'image3d') {
            if (! PlanEntitlements::hasImage3dPlan($user)) {
                return response()->json([
                    'message' => 'Upgrade your plan to use Image-to-3D.',
                    'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
                ], 403);
            }
            if (PlanEntitlements::image3dRemaining($user) <= 0) {
                return response()->json([
                    'message' => 'Image-to-3D monthly limit reached for your plan.',
                    'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
                ], 429);
            }
            if (! $consume) {
                return response()->json([
                    'ok' => true,
                    'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
                ]);
            }
            if (! PlanEntitlements::consumeImage3d($user)) {
                return response()->json([
                    'message' => 'Image-to-3D monthly limit reached for your plan.',
                    'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
                ], 429);
            }
        } elseif ($data['feature'] === 'ai_chat') {
            if (! PlanEntitlements::consumeAiChat($user)) {
                return response()->json([
                    'message' => 'AI chat monthly limit reached for your plan.',
                    'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
                ], 429);
            }
        }

        return response()->json([
            'ok' => true,
            'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
        ]);
    }
}
