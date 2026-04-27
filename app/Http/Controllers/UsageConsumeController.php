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
        ]);
        $user = $request->user();

        if ($data['feature'] === 'image3d') {
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
