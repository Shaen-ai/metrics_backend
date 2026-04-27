<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalUsageConsumeController extends Controller
{
    public function consume(Request $request): JsonResponse
    {
        $expected = config('services.internal_api_key');
        if (! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'Internal usage API not configured.'], 503);
        }
        if ($request->header('X-Internal-Key') !== $expected) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'slug' => 'required|string',
            'feature' => 'required|string|in:image3d,ai_chat',
        ]);

        $user = User::where('slug', $data['slug'])->first();
        if (! $user) {
            return response()->json(['message' => 'Unknown storefront.'], 404);
        }

        if ($data['feature'] === 'image3d') {
            if (! PlanEntitlements::consumeImage3d($user)) {
                return response()->json([
                    'message' => 'Image-to-3D monthly limit reached for this workspace.',
                    'entitlements' => PlanEntitlements::toPublicArray($user->fresh()),
                ], 429);
            }
        } else {
            if (! PlanEntitlements::consumeAiChat($user)) {
                return response()->json([
                    'message' => 'AI chat monthly limit reached for this workspace.',
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
