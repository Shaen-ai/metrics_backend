<?php

namespace App\Support;

use App\Models\User;
use Carbon\Carbon;
class PlanEntitlements
{
    public static function onActiveTrial(User $user): bool
    {
        return $user->trial_ends_at !== null && Carbon::now()->lt($user->trial_ends_at);
    }

    /** @return array<string, mixed> */
    public static function basePlanRow(User $user): array
    {
        $tier = $user->plan_tier ?? 'free';
        $row = config("plans.{$tier}");
        if (! is_array($row)) {
            $row = config('plans.free');
        }

        if (($tier === 'free') && self::onActiveTrial($user)) {
            $trialRow = config('plans.growth');
            if (is_array($trialRow)) {
                $row = $trialRow;
            }
        }

        return $row;
    }

    public static function inFirstImage3dBonusWindow(User $user): bool
    {
        return Carbon::parse($user->created_at)->diffInDays(Carbon::now()) < 30;
    }

    public static function image3dMonthlyCap(User $user): int
    {
        $row = self::basePlanRow($user);
        $key = self::inFirstImage3dBonusWindow($user) ? 'image3d_first_month' : 'image3d_ongoing';

        return (int) ($row[$key] ?? 0);
    }

    public static function aiChatMonthlyCap(User $user): ?int
    {
        $v = self::basePlanRow($user)['ai_chat_monthly'] ?? 20;

        return $v === null ? null : (int) $v;
    }

    public static function allowsPublishedLayouts(User $user): bool
    {
        return (bool) (self::basePlanRow($user)['published_layouts'] ?? false);
    }

    public static function allowsCustomTheme(User $user): bool
    {
        return (bool) (self::basePlanRow($user)['custom_theme'] ?? false);
    }

    public static function allowsBespokeDesign(User $user): bool
    {
        return (bool) (self::basePlanRow($user)['bespoke_design'] ?? false);
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
        $aiCap = self::aiChatMonthlyCap($user);
        $imgCap = self::image3dMonthlyCap($user);

        return [
            'planTier' => $user->plan_tier,
            'trialEndsAt' => $user->trial_ends_at?->toISOString(),
            'onTrial' => self::onActiveTrial($user),
            'aiChatMonthlyLimit' => $aiCap,
            'aiChatRemaining' => self::aiChatRemaining($user),
            'image3dMonthlyLimit' => $imgCap,
            'image3dRemaining' => self::image3dRemaining($user),
            'inFirstImage3dBonusWindow' => self::inFirstImage3dBonusWindow($user),
            'publishedLayouts' => self::allowsPublishedLayouts($user),
            'customTheme' => self::allowsCustomTheme($user),
            'bespokeDesign' => self::allowsBespokeDesign($user),
        ];
    }

    public static function consumeImage3d(User $user): bool
    {
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
