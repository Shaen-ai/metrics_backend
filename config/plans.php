<?php

/**
 * Plan entitlements — keep in sync with landing/src/lib/pricing-data.ts (marketing).
 *
 * - image3d_first_month / image3d_ongoing: monthly caps (first 30 days after signup use first_month)
 * - ai_chat_monthly: null = unlimited
 *
 * Future: Stripe Billing webhooks (e.g. subscription.updated) can map Price IDs to plan_tier here
 * and set users.plan_tier; expose Customer Portal as NEXT_PUBLIC_BILLING_PORTAL_URL in metrics_platform.
 */
return [
    'free' => [
        'image3d_first_month' => 10,
        'image3d_ongoing' => 3,
        'ai_chat_monthly' => 20,
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
    'growth' => [
        'image3d_first_month' => 200,
        'image3d_ongoing' => 55,
        'ai_chat_monthly' => 200,
        'priority_processing' => false,
        'custom_domain' => true,
        'published_layouts' => false,
        'custom_theme' => false,
        'bespoke_design' => false,
    ],
    'scale' => [
        'image3d_first_month' => 400,
        'image3d_ongoing' => 100,
        'ai_chat_monthly' => null,
        'priority_processing' => true,
        'custom_domain' => true,
        'published_layouts' => false,
        'custom_theme' => false,
        'bespoke_design' => false,
    ],
    /** Business Pro — same entitlements as Scale; used for migrated / premium workspaces. */
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
