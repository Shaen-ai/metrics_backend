<?php

/**
 * Plan entitlements — keep in sync with landing/src/lib/pricing-data.ts (marketing).
 *
 * Paid tier keys (plan_tier): starter, business, business_pro, enterprise.
 *
 * - image3d_first_month / image3d_ongoing: caps — first_month applies for 30 days after
 *   users.image3d_bonus_anchor_at (set on Stripe subscribe / plan change), else created_at.
 * - ai_chat_monthly: null = unlimited
 * - unsubscribed: no Stripe subscription (plan_tier "free"); all usage blocked — there is no free product tier.
 *
 * tiers_without_stripe_subscription: plan_tier slugs with full access without stripe_subscription_id (e.g. manual Enterprise invoices).
 *
 * Future: Stripe Billing webhooks map Price IDs to plan_tier here
 * and set users.plan_tier; expose Customer Portal as NEXT_PUBLIC_BILLING_PORTAL_URL in metrics_platform.
 */
return [
    'tiers_without_stripe_subscription' => [
        'enterprise',
    ],

    'unsubscribed' => [
        'image3d_first_month' => 0,
        'image3d_ongoing' => 0,
        'ai_chat_monthly' => 0,
        'priority_processing' => false,
        'custom_domain' => false,
        'published_layouts' => false,
        'custom_theme' => false,
        'bespoke_design' => false,
    ],
    'starter' => [
        'image3d_first_month' => 100,
        'image3d_ongoing' => 25,
        'ai_chat_monthly' => 50,
        'priority_processing' => false,
        'custom_domain' => false,
        'published_layouts' => false,
        'custom_theme' => false,
        'bespoke_design' => false,
    ],
    'business' => [
        'image3d_first_month' => 200,
        'image3d_ongoing' => 55,
        'ai_chat_monthly' => 200,
        'priority_processing' => false,
        'custom_domain' => true,
        'published_layouts' => false,
        'custom_theme' => false,
        'bespoke_design' => false,
    ],
    /** Business Pro — higher quotas and advanced publishing/theme; migrated / premium workspaces. */
    'business_pro' => [
        'image3d_first_month' => 400,
        'image3d_ongoing' => 100,
        'ai_chat_monthly' => null,
        'priority_processing' => true,
        'custom_domain' => true,
        'published_layouts' => true,
        'custom_theme' => true,
        'bespoke_design' => false,
    ],
    'enterprise' => [
        'image3d_first_month' => 999999,
        'image3d_ongoing' => 999999,
        'ai_chat_monthly' => null,
        'priority_processing' => true,
        'custom_domain' => true,
        'published_layouts' => true,
        'custom_theme' => true,
        'bespoke_design' => true,
    ],
];
