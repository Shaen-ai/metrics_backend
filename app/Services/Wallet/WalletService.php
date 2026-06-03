<?php

namespace App\Services\Wallet;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public const INTERIOR_DESIGN_COST_USD = 0.33;

    /**
     * Charge the user's wallet for an interior design generation.
     * Returns true if the charge was successful, false if insufficient balance.
     */
    public function chargeInteriorDesign(User $user): bool
    {
        return $this->charge($user, self::INTERIOR_DESIGN_COST_USD, 'interior_design', 'Interior design generation');
    }

    /**
     * Deduct a specific amount from the user's wallet.
     */
    public function charge(User $user, float $amount, string $type, string $description, array $metadata = []): bool
    {
        return DB::transaction(function () use ($user, $amount, $type, $description, $metadata) {
            $user = User::lockForUpdate()->find($user->id);
            if (! $user) {
                return false;
            }

            $balance = (float) $user->wallet_balance_usd;

            if ($balance < $amount) {
                Log::info('Wallet charge failed: insufficient balance', [
                    'user_id' => $user->id,
                    'balance' => $balance,
                    'required' => $amount,
                    'type' => $type,
                ]);

                return false;
            }

            $newBalance = round($balance - $amount, 2);

            $user->update(['wallet_balance_usd' => $newBalance]);

            WalletTransaction::create([
                'user_id' => $user->id,
                'type' => $type,
                'amount_usd' => -$amount,
                'balance_after_usd' => $newBalance,
                'description' => $description,
                'metadata' => $metadata ?: null,
            ]);

            return true;
        });
    }

    /**
     * Add funds to the user's wallet (after successful Stripe payment).
     */
    public function topUp(User $user, float $amount, ?string $stripePaymentIntentId = null, string $description = 'Wallet top-up'): WalletTransaction
    {
        return DB::transaction(function () use ($user, $amount, $stripePaymentIntentId, $description) {
            $user = User::lockForUpdate()->find($user->id);
            $balance = (float) $user->wallet_balance_usd;
            $newBalance = round($balance + $amount, 2);

            $user->update(['wallet_balance_usd' => $newBalance]);

            return WalletTransaction::create([
                'user_id' => $user->id,
                'type' => 'top_up',
                'amount_usd' => $amount,
                'balance_after_usd' => $newBalance,
                'description' => $description,
                'stripe_payment_intent_id' => $stripePaymentIntentId,
            ]);
        });
    }

    public function getBalance(User $user): float
    {
        return (float) $user->wallet_balance_usd;
    }

    public function getTransactionHistory(User $user, int $limit = 50): Collection
    {
        return $user->walletTransactions()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
