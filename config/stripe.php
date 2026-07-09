<?php

/**
 * Stripe subscription checkout — price IDs must match Dashboard products.
 * plan_tier values align with config/plans.php keys.
 */
$priceToTier = [];
foreach ([
    'STRIPE_PRICE_STARTER_MONTHLY' => 'starter',
    'STRIPE_PRICE_STARTER_YEARLY' => 'starter',
    'STRIPE_PRICE_BUSINESS_MONTHLY' => 'business',
    'STRIPE_PRICE_BUSINESS_YEARLY' => 'business',
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

    /** Checkout Session subscription trial (days). Set 0 to disable. */
    'subscription_trial_days' => max(0, (int) env('STRIPE_SUBSCRIPTION_TRIAL_DAYS', 0)),

    /**
     * Vista token balance top-up — one-time Checkout (AMD + USD products).
     * Uses each product's default Price when set; otherwise inline custom_unit_amount. Tax added on top.
     */
    'token_topup_product_id' => trim((string) env('STRIPE_PRODUCT_TOKEN_TOPUP', 'prod_UXvrvDxfFdINyo')),
    'token_topup_product_id_usd' => trim((string) env('STRIPE_PRODUCT_TOKEN_TOPUP_USD', 'prod_UpU0k9BJnYtDay')),
    /** Default checkout currency when the client does not send `currency` (amd | usd). */
    'token_topup_currency' => strtolower(trim((string) env('STRIPE_TOKEN_TOPUP_CURRENCY', 'amd'))) ?: 'amd',

    /** Stripe Checkout UI language for Vista token top-up (`auto` = browser locale, or e.g. `ru`). */
    'vista_checkout_locale' => trim((string) env('STRIPE_VISTA_CHECKOUT_LOCALE', 'auto')) ?: 'auto',
];
