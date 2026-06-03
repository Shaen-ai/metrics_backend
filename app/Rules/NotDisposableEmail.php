<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Blocks common disposable email domains (subset + extensible via config).
 */
class NotDisposableEmail implements ValidationRule
{
    /** @var list<string> */
    private static array $extraDomains = [];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! str_contains($value, '@')) {
            return;
        }

        $domain = strtolower(trim(substr($value, (int) strrpos($value, '@') + 1)));
        if ($domain === '') {
            return;
        }

        $blocked = array_merge(self::commonDomains(), config('disposable_email.domains', []));
        if (in_array($domain, $blocked, true)) {
            $fail('Disposable email addresses are not allowed. Please use a permanent email.');

            return;
        }

        foreach ($blocked as $blockedDomain) {
            if (str_ends_with($domain, '.'.$blockedDomain)) {
                $fail('Disposable email addresses are not allowed. Please use a permanent email.');

                return;
            }
        }
    }

    /** @return list<string> */
    private static function commonDomains(): array
    {
        return [
            'mailinator.com',
            'guerrillamail.com',
            'guerrillamail.net',
            'sharklasers.com',
            'grr.la',
            '10minutemail.com',
            '10minutemail.net',
            'tempmail.com',
            'temp-mail.org',
            'throwaway.email',
            'yopmail.com',
            'trashmail.com',
            'getnada.com',
            'maildrop.cc',
            'dispostable.com',
            'fakeinbox.com',
            'mailnesia.com',
            'tempail.com',
            'emailondeck.com',
        ];
    }
}
