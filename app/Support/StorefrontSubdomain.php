<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class StorefrontSubdomain
{
    /** Max length for a single DNS label (RFC 1035 practical limit). */
    public const MAX_LENGTH = 63;

    private const REGEX = '/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?$/';

    public static function formatIsValid(string $slug): bool
    {
        if (strlen($slug) < 1 || strlen($slug) > self::MAX_LENGTH) {
            return false;
        }

        return (bool) preg_match(self::REGEX, $slug);
    }

    public static function isReserved(string $slug): bool
    {
        $reserved = config('storefront.reserved_subdomains', []);

        return in_array(strtolower($slug), array_map('strtolower', $reserved), true);
    }

    /**
     * Validation rules for `slug` when updating a user; omit with `sometimes` at merge site.
     *
     * @return array<int, mixed>
     */
    public static function slugRules(string $ignoreUserId): array
    {
        return [
            'string',
            'min:1',
            'max:'.self::MAX_LENGTH,
            'regex:'.self::REGEX,
            Rule::unique('users', 'slug')->ignore($ignoreUserId, 'id'),
        ];
    }
}
