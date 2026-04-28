<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\ForgotPasswordRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\ResetPasswordRequest;
use App\Http\Requests\SignupRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmailMailable;
use App\Models\User;
use App\Support\AuditLogger;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(SignupRequest $request): JsonResponse
    {
        $plainToken = Str::random(64);
        /** @var User|null $registered */
        $registered = null;

        try {
            $registered = DB::transaction(function () use ($request, $plainToken) {
                return User::create([
                    'id' => Str::uuid()->toString(),
                    'email' => $request->email,
                    'password' => $request->password,
                    'name' => $request->name,
                    'company_name' => $request->company_name,
                    'slug' => $this->uniqueSlugForCompany($request->company_name),
                    'language' => 'en',
                    'currency' => 'AMD',
                    'plan_tier' => 'free',
                    'trial_ends_at' => Carbon::now()->addDays(30),
                    'email_verified_at' => null,
                    'email_verification_token' => Hash::make($plainToken),
                ]);
            });

            $apiBase = rtrim((string) config('app.api_public_url'), '/');
            /** Email links use `/api/` — same routes as `routes/api.php` (nginx must forward `/api/*` here). */
            $verificationUrl = $apiBase.'/api/auth/verify-email?'.http_build_query([
                'email' => $registered->email,
                'token' => $plainToken,
            ]);

            Mail::to($registered->email)->send(new VerifyEmailMailable($registered, $verificationUrl));
        } catch (\Throwable $e) {
            if ($registered !== null) {
                try {
                    $registered->delete();
                } catch (\Throwable $deleteError) {
                    report($deleteError);
                }
            }
            report($e);

            return response()->json([
                'message' => 'Registration could not be completed. If this persists, contact support.',
            ], 503);
        }

        return response()->json([
            'message' => 'Check your email for a link to verify your account before signing in.',
        ], 201);
    }

    /**
     * `slug` is unique. Pure `Str::slug($company)` collides for identical names; a short
     * random suffix keeps signups safe under concurrency (no two rows with the same slug).
     */
    private function uniqueSlugForCompany(string $companyName): string
    {
        $base = Str::slug($companyName);
        if ($base === '') {
            $base = 'store';
        }

        for ($i = 0; $i < 8; $i++) {
            $slug = $base.'-'.Str::lower(Str::random(6));
            if (! User::where('slug', $slug)->exists()) {
                return $slug;
            }
        }

        return $base.'-'.Str::uuid()->toString();
    }

    public function verifyEmail(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
        ]);

        $user = User::where('email', $request->email)->first();
        $frontend = rtrim(config('app.frontend_admin_url'), '/');

        if (! $user) {
            return redirect()->away($frontend.'/login?verification=invalid');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->away($frontend.'/login?verification=already');
        }

        if (! $user->email_verification_token
            || ! Hash::check((string) $request->input('token'), $user->email_verification_token)
        ) {
            return redirect()->away($frontend.'/login?verification=invalid');
        }

        $user->forceFill([
            'email_verified_at' => now(),
            'email_verification_token' => null,
        ])->save();

        return redirect()->away($frontend.'/login?verified=1');
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid email or password',
            ], 401);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Please verify your email using the link we sent you before signing in.',
            ], 403);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => new UserResource($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => new UserResource($request->user()),
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();
        if (array_key_exists('planner_material_ids', $data) && $data['planner_material_ids'] === []) {
            $data['planner_material_ids'] = null;
        }
        $user->update($data);

        AuditLogger::log($request, $user, 'profile.updated', User::class, $user->id);

        return response()->json([
            'user' => new UserResource($user->fresh()),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        Password::sendResetLink($request->only('email'));

        return response()->json([
            'message' => 'If that email is registered, you will receive a link to reset your password.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user) use ($request) {
                $user->forceFill([
                    'password' => $request->password,
                ])->setRememberToken(Str::random(60));
                $user->save();
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $message = match ($status) {
                Password::INVALID_TOKEN, Password::RESET_THROTTLED => 'This link is invalid or has expired. Request a new password reset.',
                default => 'Unable to reset password. Please try again.',
            };

            return response()->json(['message' => $message], 422);
        }

        return response()->json(['message' => 'Password has been reset. You can now sign in.']);
    }
}
