<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;

class PlanEntitlements
{
    /** Map legacy DB/config slugs to canonical plan_tier keys. */
    public static function normalizePlanTier(?string $raw): string
    {
        $t = $raw ?? 'free';

        return match ($t) {
            'growth' => 'business',
            'scale' => 'business_pro',
            default => $t,
        };
    }

    /**
     * True when Stripe subscription exists, or billing tier stored on the user matches a paid row in config/plans.php.
     * The latter covers enterprise invoices, migrations (e.g. business_pro grandfather), and Stripe↔DB sync lag where
     * plan_tier is set before stripe_subscription_id is written.
     */
    public static function hasActiveSubscription(User $user): bool
    {
        if (($user->stripe_subscription_id ?? '') !== '') {
            return true;
        }

        return self::hasPaidPlanTierInConfig($user->plan_tier ?? 'free');
    }

    /** plan_tier keys that map to a real plan row (not `free`; excludes meta key `unsubscribed`). */
    private static function hasPaidPlanTierInConfig(string $tier): bool
    {
        $tier = self::normalizePlanTier($tier);
        if ($tier === '' || $tier === 'free' || $tier === 'unsubscribed') {
            return false;
        }

        return is_array(config("plans.{$tier}"));
    }

    /** Stripe Billing trial window (requires payment method — no cardless product tier). */
    public static function onActiveTrial(User $user): bool
    {
        if (! self::hasActiveSubscription($user)) {
            return false;
        }

        return $user->trial_ends_at !== null && Carbon::now()->lt($user->trial_ends_at);
    }

    /** @return array<string, mixed> */
    public static function basePlanRow(User $user): array
    {
        if (! self::hasActiveSubscription($user)) {
            $unsubscribed = config('plans.unsubscribed');
            if (is_array($unsubscribed)) {
                return $unsubscribed;
            }

            return [];
        }

        $tier = self::normalizePlanTier($user->plan_tier ?? 'free');
        $row = config("plans.{$tier}");
        if (! is_array($row)) {
            $row = config('plans.unsubscribed');
        }

        return is_array($row) ? $row : [];
    }

    public static function inFirstImage3dBonusWindow(User $user): bool
    {
        $anchor = $user->image3d_bonus_anchor_at ?? $user->created_at;
        if ($anchor === null) {
            return false;
        }

        return Carbon::parse($anchor)->diffInDays(Carbon::now()) < 30;
    }

    public static function image3dMonthlyCap(User $user): int
    {
        $row = self::basePlanRow($user);
        $key = self::inFirstImage3dBonusWindow($user) ? 'image3d_first_month' : 'image3d_ongoing';

        return (int) ($row[$key] ?? 0);
    }

    public static function aiChatMonthlyCap(User $user): ?int
    {
        $row = self::basePlanRow($user);
        if (! array_key_exists('ai_chat_monthly', $row)) {
            return 0;
        }
        $v = $row['ai_chat_monthly'];

        return $v === null ? null : (int) $v;
    }

    public static function allowsPublishedLayouts(User $user): bool
    {
        return self::hasActiveSubscription($user)
            && (bool) (self::basePlanRow($user)['published_layouts'] ?? false);
    }

    public static function allowsCustomTheme(User $user): bool
    {
        return self::hasActiveSubscription($user)
            && (bool) (self::basePlanRow($user)['custom_theme'] ?? false);
    }

    public static function allowsBespokeDesign(User $user): bool
    {
        return self::hasActiveSubscription($user)
            && (bool) (self::basePlanRow($user)['bespoke_design'] ?? false);
    }

    public static function normalizeUsageMonth(User $user): void
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $stored = $user->usage_month_start
            ? Carbon::parse($user->usage_month_start)->toDateString()
            : null;
        if ($stored !== $start) {
            $user->forceFill([
                'usage_month_start' => $start,
                'image3d_generations_this_month' => 0,
                'ai_chat_messages_this_month' => 0,
            ])->saveQuietly();
        }
    }

    public static function image3dRemaining(User $user): int
    {
        self::normalizeUsageMonth($user);
        $user->refresh();
        $cap = self::image3dMonthlyCap($user);

        return max(0, $cap - (int) $user->image3d_generations_this_month);
    }

    public static function aiChatRemaining(User $user): ?int
    {
        self::normalizeUsageMonth($user);
        $user->refresh();
        $cap = self::aiChatMonthlyCap($user);
        if ($cap === null) {
            return null;
        }

        return max(0, $cap - (int) $user->ai_chat_messages_this_month);
    }

    /**
     * @return array<string, mixed>
     */
    public static function toPublicArray(User $user): array
    {
        self::normalizeUsageMonth($user);
        $user->refresh();
        $hasSub = self::hasActiveSubscription($user);
        $aiCap = self::aiChatMonthlyCap($user);
        $imgCap = self::image3dMonthlyCap($user);

        return [
            'planTier' => self::normalizePlanTier($user->plan_tier ?? 'free'),
            'trialEndsAt' => $user->trial_ends_at?->toISOString(),
            'onTrial' => self::onActiveTrial($user),
            'subscriptionActive' => $hasSub,
            'aiChatMonthlyLimit' => $aiCap,
            'aiChatRemaining' => self::aiChatRemaining($user),
            'image3dMonthlyLimit' => $imgCap,
            'image3dRemaining' => self::image3dRemaining($user),
            'inFirstImage3dBonusWindow' => $hasSub && self::inFirstImage3dBonusWindow($user),
            'publishedLayouts' => self::allowsPublishedLayouts($user),
            'customTheme' => self::allowsCustomTheme($user),
            'bespokeDesign' => self::allowsBespokeDesign($user),
        ];
    }

    public static function consumeImage3d(User $user): bool
    {
        if (! self::hasActiveSubscription($user)) {
            return false;
        }
        self::normalizeUsageMonth($user);
        $user->refresh();
        if (self::image3dRemaining($user) <= 0) {
            return false;
        }
        $user->increment('image3d_generations_this_month');

        return true;
    }

    public static function consumeAiChat(User $user): bool
    {
        if (! self::hasActiveSubscription($user)) {
            return false;
        }
        self::normalizeUsageMonth($user);
        $user->refresh();
        $cap = self::aiChatMonthlyCap($user);
        if ($cap === null) {
            $user->increment('ai_chat_messages_this_month');

            return true;
        }
        if ((int) $user->ai_chat_messages_this_month >= $cap) {
            return false;
        }
        $user->increment('ai_chat_messages_this_month');

        return true;
    }
}
