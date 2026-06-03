<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Services\Tokens\TokenService;
use App\Support\SocialAuthHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;

class SocialAuthController extends Controller
{
    private const EXCHANGE_TTL_SECONDS = 300;

    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    public function redirect(Request $request, string $provider): RedirectResponse|Response
    {
        if ($provider !== 'google') {
            abort(404);
        }

        if (! $this->googleConfigured()) {
            return $this->redirectWithError(
                SocialAuthHelper::frontendBaseForIntent(
                    SocialAuthHelper::normalizeIntent($request->query('intent'))
                ),
                'oauth_not_configured',
            );
        }

        $intent = SocialAuthHelper::normalizeIntent($request->query('intent'));
        $referralCode = $request->query('ref') ?? $request->query('referralCode');

        return Socialite::driver('google')
            ->stateless()
            ->scopes(['openid', 'profile', 'email'])
            ->with(['state' => SocialAuthHelper::encodeOAuthState($intent, is_string($referralCode) ? $referralCode : null)])
            ->redirect();
    }

    public function callback(Request $request, string $provider): RedirectResponse|Response
    {
        if ($provider !== 'google') {
            abort(404);
        }

        $oauthPayload = SocialAuthHelper::payloadFromOAuthState($request->input('state'));
        $intent = $oauthPayload['intent'];
        $oauthReferralCode = $oauthPayload['referralCode'];
        $frontend = SocialAuthHelper::frontendBaseForIntent($intent);

        if (! $this->googleConfigured()) {
            return $this->redirectWithError($frontend, 'oauth_not_configured');
        }

        try {
            $socialUser = Socialite::driver('google')->stateless()->user();
        } catch (\Throwable $e) {
            report($e);

            return $this->redirectWithError($frontend, 'oauth_failed');
        }

        $email = strtolower(trim((string) $socialUser->getEmail()));
        if ($email === '') {
            return $this->redirectWithError($frontend, 'email_required');
        }

        $googleId = (string) $socialUser->getId();
        $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname() ?: ''));

        $user = User::where('google_id', $googleId)->first();

        if ($user === null) {
            $user = User::where('email', $email)->first();
            if ($user !== null) {
                if ($user->google_id !== null && $user->google_id !== $googleId) {
                    return $this->redirectWithError($frontend, 'account_conflict');
                }
                $user->forceFill(['google_id' => $googleId])->save();
            }
        }

        if ($user === null) {
            $disposableCheck = Validator::make(
                ['email' => $email],
                ['email' => [new NotDisposableEmail]],
            );
            if ($disposableCheck->fails()) {
                return $this->redirectWithError($frontend, 'disposable_email');
            }

            $companyName = SocialAuthHelper::companyNameFromProfile($name, $email);
            $displayName = $name !== '' ? $name : $companyName;

            $user = User::create([
                'id' => Str::uuid()->toString(),
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'name' => $displayName,
                'company_name' => $companyName,
                'slug' => SocialAuthHelper::uniqueSlug($companyName),
                'google_id' => $googleId,
                'user_type' => $intent === 'vista' ? 'consumer' : 'business',
                'language' => 'en',
                'currency' => 'AMD',
                'plan_tier' => 'free',
                'trial_ends_at' => null,
                'email_verified_at' => now(),
                'email_verification_token' => null,
            ]);

            if ($intent === 'vista' && $oauthReferralCode) {
                $this->tokenService->applyReferralOnRegister($user, $oauthReferralCode);
            }
        } elseif ($user->email_verified_at === null) {
            $user->forceFill([
                'email_verified_at' => now(),
                'email_verification_token' => null,
            ])->save();
        }

        $code = Str::random(64);
        Cache::put($this->exchangeCacheKey($code), $user->id, self::EXCHANGE_TTL_SECONDS);

        return redirect()->away($frontend.'/auth/callback?'.http_build_query(['code' => $code]));
    }

    public function exchange(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'min:32', 'max:128'],
        ]);

        $cacheKey = $this->exchangeCacheKey($data['code']);
        $userId = Cache::pull($cacheKey);

        if (! is_string($userId) || $userId === '') {
            return response()->json([
                'message' => 'This sign-in link is invalid or has expired. Please try again.',
            ], 422);
        }

        $user = User::find($userId);
        if ($user === null) {
            return response()->json([
                'message' => 'Account not found.',
            ], 404);
        }

        $user = $this->tokenService->processPostAuth(
            $user,
            $request->header('X-Vista-Device-Id'),
            $request->input('referralCode'),
        );

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    private function googleConfigured(): bool
    {
        $id = config('services.google.client_id');
        $secret = config('services.google.client_secret');

        return is_string($id) && $id !== '' && is_string($secret) && $secret !== '';
    }

    private function exchangeCacheKey(string $code): string
    {
        return 'oauth_exchange:'.hash('sha256', $code);
    }

    private function redirectWithError(string $frontend, string $error): RedirectResponse
    {
        return redirect()->away($frontend.'/auth/callback?'.http_build_query(['error' => $error]));
    }
}
