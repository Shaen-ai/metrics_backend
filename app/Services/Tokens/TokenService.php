<?php

namespace App\Services\Tokens;

use App\Models\AnonymousTokenSession;
use App\Models\Referral;
use App\Models\TokenTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TokenService
{
    public function costForAction(string $action): int
    {
        $costs = config('tokens.costs', []);

        return (int) ($costs[$action] ?? 0);
    }

    public function amdPerToken(): int
    {
        return (int) config('tokens.amd_per_token', 40);
    }

    public function ensureReferralCode(User $user): User
    {
        if (is_string($user->referral_code) && $user->referral_code !== '') {
            return $user;
        }

        for ($i = 0; $i < 12; $i++) {
            $code = Str::lower(Str::random(8));
            if (! User::where('referral_code', $code)->exists()) {
                $user->forceFill(['referral_code' => $code])->save();

                return $user->fresh();
            }
        }

        $user->forceFill(['referral_code' => Str::lower(Str::random(12))])->save();

        return $user->fresh();
    }

    public function resolveReferralCode(?string $code): ?User
    {
        $normalized = strtolower(trim((string) $code));
        if ($normalized === '') {
            return null;
        }

        return User::where('referral_code', $normalized)->first();
    }

    public function referralUrl(User $user): string
    {
        $user = $this->ensureReferralCode($user);
        $base = rtrim((string) config('app.frontend_vista_url'), '/');

        return $base.'/invite/'.$user->referral_code;
    }

    public function grantAnonymousIfNeeded(string $deviceId): array
    {
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($deviceId === null) {
            return ['balance' => 0, 'granted' => false];
        }

        return DB::transaction(function () use ($deviceId) {
            $session = AnonymousTokenSession::lockForUpdate()->find($deviceId);

            if ($session === null) {
                $grant = (int) config('tokens.anonymous_grant', 20);
                $session = AnonymousTokenSession::create([
                    'device_id' => $deviceId,
                    'token_balance' => $grant,
                    'granted_at' => now(),
                    'last_seen_at' => now(),
                ]);
                $this->recordTransaction(null, $deviceId, 'anonymous_grant', $grant, $grant, 'Anonymous welcome tokens');

                return ['balance' => $grant, 'granted' => true];
            }

            $session->update(['last_seen_at' => now()]);
            $session = $this->topUpAnonymousForLocalDev($session);

            return ['balance' => (int) $session->token_balance, 'granted' => false];
        });
    }

    public function getBalance(?User $user, ?string $deviceId): array
    {
        $deviceId = $this->normalizeDeviceId($deviceId);

        if ($user !== null) {
            $user = $this->topUpUserForLocalDev($user);

            return [
                'balance' => (int) $user->token_balance,
                'isAnonymous' => false,
                'amdPerToken' => $this->amdPerToken(),
            ];
        }

        if ($deviceId === null) {
            return [
                'balance' => 0,
                'isAnonymous' => true,
                'amdPerToken' => $this->amdPerToken(),
            ];
        }

        $session = AnonymousTokenSession::find($deviceId);
        if ($session !== null) {
            $session = $this->topUpAnonymousForLocalDev($session);
            $session->update(['last_seen_at' => now()]);
        }

        return [
            'balance' => (int) ($session?->token_balance ?? 0),
            'isAnonymous' => true,
            'amdPerToken' => $this->amdPerToken(),
        ];
    }

    /**
     * Verify balance for an action without deducting (used before long-running AI work).
     *
     * @return array{ok: true, balance: int}|array{ok: false, balance: int, required: int, message: string}
     */
    public function canConsume(?User $user, ?string $deviceId, string $action): array
    {
        $cost = $this->costForAction($action);
        if ($cost <= 0) {
            return ['ok' => false, 'balance' => 0, 'required' => 0, 'message' => 'Unknown token action.'];
        }

        $deviceId = $this->normalizeDeviceId($deviceId);
        $balance = (int) $this->getBalance($user, $deviceId)['balance'];

        if ($balance < $cost) {
            return [
                'ok' => false,
                'balance' => $balance,
                'required' => $cost,
                'message' => "Not enough tokens. This action costs {$cost} tokens; you have {$balance}.",
            ];
        }

        return ['ok' => true, 'balance' => $balance];
    }

    /**
     * @return array{ok: true, balance: int}|array{ok: false, balance: int, required: int, message: string}
     */
    public function consume(?User $user, ?string $deviceId, string $action): array
    {
        $cost = $this->costForAction($action);
        if ($cost <= 0) {
            return ['ok' => false, 'balance' => 0, 'required' => 0, 'message' => 'Unknown token action.'];
        }

        $deviceId = $this->normalizeDeviceId($deviceId);

        return DB::transaction(function () use ($user, $deviceId, $action, $cost) {
            if ($user !== null) {
                $user = User::lockForUpdate()->find($user->id);
                if ($user === null) {
                    return ['ok' => false, 'balance' => 0, 'required' => $cost, 'message' => 'Account not found.'];
                }

                $balance = (int) $user->token_balance;
                if ($balance < $cost) {
                    return [
                        'ok' => false,
                        'balance' => $balance,
                        'required' => $cost,
                        'message' => "Not enough tokens. This action costs {$cost} tokens; you have {$balance}.",
                    ];
                }

                $newBalance = $balance - $cost;
                $user->update(['token_balance' => $newBalance]);
                $this->recordTransaction($user->id, null, $action, -$cost, $newBalance, "Token charge: {$action}");

                return ['ok' => true, 'balance' => $newBalance];
            }

            if ($deviceId === null) {
                return [
                    'ok' => false,
                    'balance' => 0,
                    'required' => $cost,
                    'message' => 'Sign in or refresh the page to use tokens.',
                ];
            }

            $session = AnonymousTokenSession::lockForUpdate()->find($deviceId);
            if ($session === null) {
                return [
                    'ok' => false,
                    'balance' => 0,
                    'required' => $cost,
                    'message' => "Not enough tokens. This action costs {$cost} tokens; you have 0.",
                ];
            }

            $balance = (int) $session->token_balance;
            if ($balance < $cost) {
                return [
                    'ok' => false,
                    'balance' => $balance,
                    'required' => $cost,
                    'message' => "Not enough tokens. This action costs {$cost} tokens; you have {$balance}.",
                ];
            }

            $newBalance = $balance - $cost;
            $session->update(['token_balance' => $newBalance, 'last_seen_at' => now()]);
            $this->recordTransaction(null, $deviceId, $action, -$cost, $newBalance, "Token charge: {$action}");

            return ['ok' => true, 'balance' => $newBalance];
        });
    }

    public function mergeAnonymousOnLogin(User $user, ?string $deviceId): User
    {
        $deviceId = $this->normalizeDeviceId($deviceId);
        if ($deviceId === null) {
            return $user;
        }

        return DB::transaction(function () use ($user, $deviceId) {
            $user = User::lockForUpdate()->find($user->id);
            $session = AnonymousTokenSession::lockForUpdate()->find($deviceId);

            if ($session === null || (int) $session->token_balance <= 0) {
                return $user;
            }

            $transfer = (int) $session->token_balance;
            $newBalance = (int) $user->token_balance + $transfer;
            $user->update(['token_balance' => $newBalance]);
            $session->update(['token_balance' => 0]);
            $this->recordTransaction($user->id, $deviceId, 'anonymous_merge', $transfer, $newBalance, 'Merged anonymous tokens on login');

            return $user->fresh();
        });
    }

    public function applyReferralOnRegister(User $user, ?string $referralCode): User
    {
        if ($user->referred_by_user_id !== null) {
            return $user;
        }

        $referrer = $this->resolveReferralCode($referralCode);
        if ($referrer === null || $referrer->id === $user->id) {
            return $user;
        }

        $user->forceFill(['referred_by_user_id' => $referrer->id])->save();

        return $user->fresh();
    }

    public function grantFirstLoginBonus(User $user): User
    {
        return DB::transaction(function () use ($user) {
            $user = User::lockForUpdate()->find($user->id);
            if ($user === null) {
                return $user;
            }

            if ($user->first_login_bonus_granted_at !== null) {
                return $user;
            }

            $user = $this->ensureReferralCode($user);

            $isReferred = $user->referred_by_user_id !== null;
            $bonus = $isReferred
                ? (int) config('tokens.referral_invitee_bonus', 40)
                : (int) config('tokens.login_bonus', 20);

            $newBalance = (int) $user->token_balance + $bonus;
            $user->forceFill([
                'token_balance' => $newBalance,
                'first_login_bonus_granted_at' => now(),
            ])->save();

            $type = $isReferred ? 'referral_invitee_bonus' : 'login_bonus';
            $this->recordTransaction($user->id, null, $type, $bonus, $newBalance, $isReferred ? 'Referral signup bonus' : 'First login bonus');

            if ($isReferred) {
                $this->creditReferrer($user);
            }

            return $user->fresh();
        });
    }

    public function processPostAuth(User $user, ?string $deviceId, ?string $referralCode): User
    {
        if ($referralCode && $user->first_login_bonus_granted_at === null && $user->referred_by_user_id === null) {
            $user = $this->applyReferralOnRegister($user, $referralCode);
        }

        $user = $this->mergeAnonymousOnLogin($user, $deviceId);
        $user = $this->grantFirstLoginBonus($user);

        return $this->ensureReferralCode($user);
    }

    private function creditReferrer(User $invitee): void
    {
        $referrerId = $invitee->referred_by_user_id;
        if ($referrerId === null) {
            return;
        }

        $existing = Referral::where('referred_user_id', $invitee->id)->first();
        if ($existing !== null && $existing->credited_at !== null) {
            return;
        }

        $referrer = User::lockForUpdate()->find($referrerId);
        if ($referrer === null || $referrer->id === $invitee->id) {
            return;
        }

        $bonus = (int) config('tokens.referral_referrer_bonus', 20);
        $cap = (int) config('tokens.referral_earnings_cap', 200);
        $earned = (int) $referrer->referral_tokens_earned;

        if ($earned + $bonus > $cap) {
            Referral::updateOrCreate(
                ['referred_user_id' => $invitee->id],
                ['referrer_id' => $referrer->id, 'credited_at' => now()]
            );

            return;
        }

        $newBalance = (int) $referrer->token_balance + $bonus;
        $referrer->forceFill([
            'token_balance' => $newBalance,
            'referral_tokens_earned' => $earned + $bonus,
        ])->save();

        $this->recordTransaction($referrer->id, null, 'referral_referrer_bonus', $bonus, $newBalance, 'Referral reward');

        Referral::updateOrCreate(
            ['referred_user_id' => $invitee->id],
            ['referrer_id' => $referrer->id, 'credited_at' => now()]
        );
    }

    /** In local dev, refill balances up to the configured welcome grant. */
    private function localDevTokenTarget(): int
    {
        return (int) config('tokens.anonymous_grant', 20);
    }

    private function topUpAnonymousForLocalDev(AnonymousTokenSession $session): AnonymousTokenSession
    {
        if (! app()->environment('local')) {
            return $session;
        }

        $target = $this->localDevTokenTarget();
        $balance = (int) $session->token_balance;
        if ($balance >= $target) {
            return $session;
        }

        $delta = $target - $balance;
        $session->update(['token_balance' => $target]);
        $this->recordTransaction(
            null,
            $session->device_id,
            'local_dev_topup',
            $delta,
            $target,
            'Local dev anonymous balance top-up',
        );

        return $session->fresh();
    }

    private function topUpUserForLocalDev(User $user): User
    {
        if (! app()->environment('local')) {
            return $user;
        }

        $target = $this->localDevTokenTarget();
        $balance = (int) $user->token_balance;
        if ($balance >= $target) {
            return $user;
        }

        $delta = $target - $balance;
        $user->update(['token_balance' => $target]);
        $this->recordTransaction(
            $user->id,
            null,
            'local_dev_topup',
            $delta,
            $target,
            'Local dev user balance top-up',
        );

        return $user->fresh();
    }

    private function recordTransaction(
        ?string $userId,
        ?string $deviceId,
        string $type,
        int $amount,
        int $balanceAfter,
        string $description,
        array $metadata = [],
    ): void {
        TokenTransaction::create([
            'user_id' => $userId,
            'device_id' => $deviceId,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balanceAfter,
            'description' => $description,
            'metadata' => $metadata ?: null,
        ]);
    }

    private function normalizeDeviceId(?string $deviceId): ?string
    {
        $deviceId = strtolower(trim((string) $deviceId));
        if ($deviceId === '' || ! Str::isUuid($deviceId)) {
            return null;
        }

        return $deviceId;
    }
}
