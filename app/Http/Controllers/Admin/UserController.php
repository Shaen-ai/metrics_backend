<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TokenTransaction;
use App\Models\User;
use App\Support\AdminMedia;
use App\Support\AuditLogger;
use App\Support\PlanEntitlements;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /** Paid tiers an admin may assign (plus free). */
    private const ASSIGNABLE_TIERS = ['free', 'starter', 'business', 'business_pro', 'enterprise'];

    /**
     * GET /api/admin/users
     * Paginated, searchable directory of every platform user with cheap per-row counts.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $type = (string) $request->query('type', '');
        $plan = (string) $request->query('plan', '');
        $perPage = min(100, max(10, (int) $request->query('per_page', 25)));

        $query = User::query()->orderByDesc('created_at');

        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function ($sub) use ($like) {
                $sub->where('email', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('company_name', 'like', $like)
                    ->orWhere('slug', 'like', $like);
            });
        }
        if (in_array($type, ['business', 'consumer'], true)) {
            $query->where('user_type', $type);
        }
        if ($plan !== '') {
            $query->where('plan_tier', $plan);
        }

        $page = $query->paginate($perPage);

        $rows = collect($page->items())->map(function (User $user) {
            $counts = AdminMedia::counts($user);

            return [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'company_name' => $user->company_name,
                'slug' => $user->slug,
                'user_type' => $user->user_type,
                'plan_tier' => PlanEntitlements::normalizePlanTier($user->plan_tier),
                'subscription_active' => ($user->stripe_subscription_id ?? '') !== '',
                'token_balance' => (int) $user->token_balance,
                'created_at' => optional($user->created_at)->toIso8601String(),
                'counts' => $counts,
            ];
        });

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
            ],
        ]);
    }

    /**
     * GET /api/admin/users/{id}
     * Full detail: profile, plan/usage entitlements, token/wallet totals, media counts.
     */
    public function show(string $id): JsonResponse
    {
        $user = User::findOrFail($id);

        return response()->json([
            'user' => [
                'id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'company_name' => $user->company_name,
                'slug' => $user->slug,
                'user_type' => $user->user_type,
                'language' => $user->language,
                'currency' => $user->currency,
                'logo' => $user->logo,
                'stripe_customer_id' => $user->stripe_customer_id,
                'stripe_subscription_id' => $user->stripe_subscription_id,
                'token_balance' => (int) $user->token_balance,
                'referral_code' => $user->referral_code,
                'created_at' => optional($user->created_at)->toIso8601String(),
                'site_published_at' => optional($user->site_published_at)->toIso8601String(),
            ],
            'plan' => PlanEntitlements::toPublicArray($user),
            'assignable_tiers' => self::ASSIGNABLE_TIERS,
            'enforcement_active' => false,
            'counts' => AdminMedia::counts($user),
        ]);
    }

    /**
     * PATCH /api/admin/users/{id}/plan
     * Directly override plan_tier (bypasses Stripe). Audit-logged old → new.
     */
    public function updatePlan(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'plan_tier' => ['required', 'string', Rule::in(self::ASSIGNABLE_TIERS)],
        ]);

        $user = User::findOrFail($id);
        $old = PlanEntitlements::normalizePlanTier($user->plan_tier);
        $new = $data['plan_tier'];

        $user->update(['plan_tier' => $new]);

        AuditLogger::log($request, $request->user(), 'admin.plan.update', 'user', $user->id, [
            'from' => $old,
            'to' => $new,
        ]);

        return response()->json([
            'ok' => true,
            'plan' => PlanEntitlements::toPublicArray($user->refresh()),
        ]);
    }

    /**
     * POST /api/admin/users/{id}/tokens
     * Grant (positive) or deduct (negative) tokens, recording a ledger row so the
     * token_transactions history stays consistent. Balance never goes below zero.
     */
    public function adjustTokens(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'integer', 'not_in:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::findOrFail($id);
        $amount = (int) $data['amount'];
        $reason = $data['reason'] ?? 'Admin token adjustment';

        $result = DB::transaction(function () use ($user, $amount, $reason) {
            $locked = User::lockForUpdate()->find($user->id);
            $before = (int) $locked->token_balance;
            $after = max(0, $before + $amount);
            $applied = $after - $before;

            $locked->update(['token_balance' => $after]);
            TokenTransaction::create([
                'user_id' => $locked->id,
                'device_id' => null,
                'type' => 'admin_grant',
                'amount' => $applied,
                'balance_after' => $after,
                'description' => $reason,
                'metadata' => ['requested_amount' => $amount],
            ]);

            return ['before' => $before, 'after' => $after, 'applied' => $applied];
        });

        AuditLogger::log($request, $request->user(), 'admin.tokens.adjust', 'user', $user->id, $result + ['reason' => $reason]);

        return response()->json([
            'ok' => true,
            'token_balance' => $result['after'],
            'applied' => $result['applied'],
        ]);
    }
}
