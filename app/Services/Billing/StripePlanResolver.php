<?php

namespace App\Services\Billing;

class StripePlanResolver
{
    public static function planTierFromPriceId(?string $priceId): ?string
    {
        if ($priceId === null || $priceId === '') {
            return null;
        }
        $map = config('stripe.price_plan_tiers', []);

        return $map[$priceId] ?? null;
    }

    /** @return array{0: string, 1: string}|null [priceId, interval month|year] */
    public static function checkoutPriceForTier(string $tier, string $interval): ?array
    {
        $prices = config('stripe.checkout_prices', []);
        if (! isset($prices[$tier][$interval])) {
            return null;
        }
        $priceId = $prices[$tier][$interval];
        if ($priceId === '' || $priceId === null) {
            return null;
        }

        return [$priceId, $interval];
    }
}
