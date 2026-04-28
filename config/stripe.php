<?php

/**
 * Stripe subscription checkout — price IDs must match Dashboard products.
 * plan_tier values align with config/plans.php keys.
 */

$priceToTier = [];
foreach ([
    'STRIPE_PRICE_STARTER_MONTHLY' => 'starter',
    'STRIPE_PRICE_STARTER_YEARLY' => 'starter',
    'STRIPE_PRICE_BUSINESS_MONTHLY' => 'growth',
    'STRIPE_PRICE_BUSINESS_YEARLY' => 'growth',
    'STRIPE_PRICE_BUSINESS_PRO_MONTHLY' => 'business_pro',
    'STRIPE_PRICE_BUSINESS_PRO_YEARLY' => 'business_pro',
] as $envKey => $tier) {
    $id = trim((string) env($envKey));
    if ($id !== '') {
        $priceToTier[$id] = $tier;
    }
}

return [
    'secret' => trim((string) env('STRIPE_SECRET')) ?: trim((string) env('STRIPE_SECRET_KEY')),

    'webhook_secret' => trim((string) env('STRIPE_WEBHOOK_SECRET')) ?: trim((string) env('STRIPE_WEBHOOK_SIGNING_SECRET')),

    /**
     * Marketing tier key (landing) → Stripe Price ID per billing interval.
     */
    'checkout_prices' => [
        'starter' => [
            'month' => trim((string) env('STRIPE_PRICE_STARTER_MONTHLY')),
            'year' => trim((string) env('STRIPE_PRICE_STARTER_YEARLY')),
        ],
        'business' => [
            'month' => trim((string) env('STRIPE_PRICE_BUSINESS_MONTHLY')),
            'year' => trim((string) env('STRIPE_PRICE_BUSINESS_YEARLY')),
        ],
        'business-pro' => [
            'month' => trim((string) env('STRIPE_PRICE_BUSINESS_PRO_MONTHLY')),
            'year' => trim((string) env('STRIPE_PRICE_BUSINESS_PRO_YEARLY')),
        ],
    ],

    /** Stripe Price ID => plan_tier slug (for webhooks). */
    'price_plan_tiers' => $priceToTier,
];
