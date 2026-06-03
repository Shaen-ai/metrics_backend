<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Tokens\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InternalTokenConsumeController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    public function consume(Request $request): JsonResponse
    {
        $expected = config('services.internal_api_key');
        if (! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'Internal token API not configured.'], 503);
        }
        if ($request->header('X-Internal-Key') !== $expected) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'action' => 'required|string|in:generate,regenerate,edit',
            'userId' => 'nullable|uuid|exists:users,id',
            'deviceId' => 'nullable|string',
        ]);

        $user = null;
        if (! empty($data['userId'])) {
            $user = User::find($data['userId']);
            if ($user === null) {
                return response()->json(['message' => 'User not found.'], 404);
            }
        }

        $deviceId = $request->header('X-Vista-Device-Id') ?: ($data['deviceId'] ?? null);

        $result = $this->tokenService->consume($user, $deviceId, $data['action']);

        if (! $result['ok']) {
            return response()->json([
                'message' => $result['message'],
                'balance' => $result['balance'],
                'required' => $result['required'],
            ], 402);
        }

        return response()->json([
            'ok' => true,
            'balance' => $result['balance'],
        ]);
    }
}
