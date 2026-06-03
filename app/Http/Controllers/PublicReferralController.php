<?php

namespace App\Http\Controllers;

use App\Services\Tokens\TokenService;
use Illuminate\Http\JsonResponse;

class PublicReferralController extends Controller
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    public function show(string $code): JsonResponse
    {
        $referrer = $this->tokenService->resolveReferralCode($code);
        if ($referrer === null) {
            return response()->json(['message' => 'Invalid referral code.'], 404);
        }

        $firstName = trim(explode(' ', (string) $referrer->name)[0] ?? '');

        return response()->json([
            'data' => [
                'valid' => true,
                'referrerFirstName' => $firstName !== '' ? $firstName : 'Your friend',
                'inviteeBonus' => (int) config('tokens.referral_invitee_bonus', 40),
            ],
        ]);
    }
}
