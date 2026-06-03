<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Tokens\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    private function resolveUser(Request $request): ?User
    {
        $user = $request->user();
        if ($user instanceof User) {
            return $user;
        }

        $plain = $request->bearerToken();
        if (! is_string($plain) || $plain === '') {
            return null;
        }

        $token = PersonalAccessToken::findToken($plain);

        return $token?->tokenable instanceof User ? $token->tokenable : null;
    }

    public function balance(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Vista-Device-Id');
        $user = $this->resolveUser($request);

        $data = $this->tokenService->getBalance($user, $deviceId);

        return response()->json(['data' => $data]);
    }

    public function grantAnonymous(Request $request): JsonResponse
    {
        $deviceId = $request->header('X-Vista-Device-Id');
        if (! is_string($deviceId) || trim($deviceId) === '') {
            return response()->json(['message' => 'X-Vista-Device-Id header is required.'], 400);
        }

        $result = $this->tokenService->grantAnonymousIfNeeded($deviceId);
        $balance = $this->tokenService->getBalance(null, $deviceId);

        return response()->json([
            'data' => array_merge($balance, [
                'granted' => $result['granted'],
            ]),
        ]);
    }

    public function check(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:generate,regenerate,edit',
        ]);

        $deviceId = $request->header('X-Vista-Device-Id');
        $user = $this->resolveUser($request);

        $result = $this->tokenService->canConsume($user, $deviceId, $data['action']);

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

    public function consume(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => 'required|string|in:generate,regenerate,edit',
        ]);

        $deviceId = $request->header('X-Vista-Device-Id');
        $user = $this->resolveUser($request);

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

    public function referralLink(Request $request): JsonResponse
    {
        $user = $request->user();
        $user = $this->tokenService->ensureReferralCode($user);

        return response()->json([
            'data' => [
                'code' => $user->referral_code,
                'url' => $this->tokenService->referralUrl($user),
                'referralTokensEarned' => (int) $user->referral_tokens_earned,
                'referralEarningsCap' => (int) config('tokens.referral_earnings_cap', 200),
            ],
        ]);
    }
}
