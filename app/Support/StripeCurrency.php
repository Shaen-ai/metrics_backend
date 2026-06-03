<?php

namespace App\Support;

/**
 * Stripe amounts are always in the currency's minor unit (see stripe.com/docs/currencies).
 * Zero-decimal currencies (e.g. JPY) use the same number for display and API amount.
 * Two-decimal currencies (including AMD) store 400.00 AMD as 40000.
 */
final class StripeCurrency
{
    /** @var list<string> */
    private const ZERO_DECIMAL = [
        'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf',
        'vnd', 'vuv', 'xaf', 'xof', 'xpf',
    ];

    public static function minorUnitExponent(string $currency): int
    {
        $currency = strtolower(trim($currency));

        // Stripe: zero-decimal currencies use API amount as-is; AMD and most others use 2 (400 AMD → 40000).
        return in_array($currency, self::ZERO_DECIMAL, true) ? 0 : 2;
    }

    /** Convert Stripe API minor units to whole major units (truncates sub-major fractions). */
    public static function toMajorUnits(int $minorUnits, string $currency): int
    {
        $exponent = self::minorUnitExponent($currency);
        if ($exponent === 0) {
            return $minorUnits;
        }

        $divisor = 10 ** $exponent;

        return intdiv($minorUnits, $divisor);
    }
}
