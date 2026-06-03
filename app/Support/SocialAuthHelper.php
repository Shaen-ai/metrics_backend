<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class SocialAuthHelper
{
    public const OAUTH_INTENTS = ['vista', 'admin'];

    public static function frontendBaseForIntent(string $intent): string
    {
        return match ($intent) {
            'vista' => rtrim((string) config('app.frontend_vista_url'), '/'),
            default => rtrim((string) config('app.frontend_admin_url'), '/'),
        };
    }

    public static function normalizeIntent(?string $intent): string
    {
        $value = strtolower(trim((string) $intent));

        return in_array($value, self::OAUTH_INTENTS, true) ? $value : 'admin';
    }

    /** Signed OAuth `state` so callback works without relying on server session cookies. */
    public static function encodeOAuthState(string $intent, ?string $referralCode = null): string
    {
        $payload = [
            'intent' => self::normalizeIntent($intent),
            'exp' => now()->addMinutes(15)->timestamp,
        ];
        $ref = strtolower(trim((string) $referralCode));
        if ($ref !== '') {
            $payload['ref'] = substr($ref, 0, 32);
        }

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @return array{intent: string, referralCode: ?string} */
    public static function payloadFromOAuthState(?string $state): array
    {
        if (! is_string($state) || $state === '') {
            return ['intent' => 'admin', 'referralCode' => null];
        }

        try {
            $payload = json_decode(Crypt::decryptString($state), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($payload) || ($payload['exp'] ?? 0) < now()->timestamp) {
                return ['intent' => 'admin', 'referralCode' => null];
            }

            $ref = isset($payload['ref']) ? strtolower(trim((string) $payload['ref'])) : null;

            return [
                'intent' => self::normalizeIntent($payload['intent'] ?? 'admin'),
                'referralCode' => $ref !== '' ? $ref : null,
            ];
        } catch (\Throwable) {
            return ['intent' => 'admin', 'referralCode' => null];
        }
    }

    public static function intentFromOAuthState(?string $state): string
    {
        return self::payloadFromOAuthState($state)['intent'];
    }

    public static function uniqueSlug(string $seed): string
    {
        $base = Str::slug($seed);
        if ($base === '') {
            $base = 'user';
        }

        for ($i = 0; $i < 8; $i++) {
            $slug = $base.'-'.Str::lower(Str::random(6));
            if (! User::where('slug', $slug)->exists() && ! StorefrontSubdomain::isReserved($slug)) {
                return $slug;
            }
        }

        do {
            $fallback = $base.'-'.Str::lower(Str::random(8));
        } while (User::where('slug', $fallback)->exists() || StorefrontSubdomain::isReserved($fallback));

        return $fallback;
    }

    public static function companyNameFromProfile(string $name, string $email): string
    {
        $trimmed = trim($name);
        if ($trimmed !== '') {
            return $trimmed;
        }

        $local = Str::before($email, '@');

        return $local !== '' ? $local : 'Account';
    }
}
