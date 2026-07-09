<?php

namespace App\Services\Tokens;

use App\Models\TokenTransaction;
use App\Models\User;
use App\Support\StripeCurrency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TokenTopUpService
{
    public function __construct(
        private readonly TokenService $tokenService,
    ) {}

    /**
     * @return array{balance: int, tokensAdded: int, alreadyCredited: bool}
     */
    public function creditFromCheckoutSession(object $session, ?User $user = null): array
    {
        if (($session->metadata->purpose ?? null) !== 'token_topup') {
            return ['balance' => 0, 'tokensAdded' => 0, 'alreadyCredited' => false];
        }

        if (($session->payment_status ?? '') !== 'paid') {
            return ['balance' => 0, 'tokensAdded' => 0, 'alreadyCredited' => false];
        }

        $sessionId = (string) ($session->id ?? '');
        if ($sessionId === '') {
            return ['balance' => 0, 'tokensAdded' => 0, 'alreadyCredited' => false];
        }

        if ($user === null) {
            $userId = $session->metadata->user_id ?? $session->client_reference_id ?? null;
            if (! is_string($userId) || $userId === '') {
                Log::notice('Token top-up: no user on session', ['session_id' => $sessionId]);

                return ['balance' => 0, 'tokensAdded' => 0, 'alreadyCredited' => false];
            }

            $user = User::find($userId);
            if ($user === null) {
                Log::notice('Token top-up: user not found', ['session_id' => $sessionId, 'user_id' => $userId]);

                return ['balance' => 0, 'tokensAdded' => 0, 'alreadyCredited' => false];
            }
        }

        $subtotalMinor = (int) ($session->amount_subtotal ?? 0);
        $currency = strtolower((string) ($session->currency ?? config('stripe.token_topup_currency', 'amd')));
        $minorUnitsPerToken = $this->minorUnitsPerToken($currency);
        $tokens = $this->tokensFromSubtotal($subtotalMinor, $currency);

        if ($tokens <= 0) {
            Log::warning('Token top-up: subtotal too small for tokens', [
                'session_id' => $sessionId,
                'amount_subtotal_minor' => $subtotalMinor,
                'amount_major' => StripeCurrency::toMajorUnits($subtotalMinor, $currency),
                'currency' => $currency,
                'minor_units_per_token' => $minorUnitsPerToken,
            ]);

            return [
                'balance' => (int) $user->token_balance,
                'tokensAdded' => 0,
                'alreadyCredited' => false,
            ];
        }

        return DB::transaction(function () use ($user, $sessionId, $tokens, $subtotalMinor, $currency, $session) {
            $existing = TokenTransaction::query()
                ->where('user_id', $user->id)
                ->where('type', 'stripe_topup')
                ->where('metadata->stripe_session_id', $sessionId)
                ->exists();

            if ($existing) {
                $fresh = User::find($user->id);

                return [
                    'balance' => (int) ($fresh?->token_balance ?? $user->token_balance),
                    'tokensAdded' => 0,
                    'alreadyCredited' => true,
                ];
            }

            $locked = User::lockForUpdate()->find($user->id);
            if ($locked === null) {
                return ['balance' => 0, 'tokensAdded' => 0, 'alreadyCredited' => false];
            }

            $customerId = $session->customer ?? null;
            if (is_string($customerId) && $customerId !== '' && $locked->stripe_customer_id !== $customerId) {
                $locked->forceFill(['stripe_customer_id' => $customerId])->save();
            }

            $newBalance = (int) $locked->token_balance + $tokens;
            $locked->update(['token_balance' => $newBalance]);

            TokenTransaction::create([
                'user_id' => $locked->id,
                'device_id' => null,
                'type' => 'stripe_topup',
                'amount' => $tokens,
                'balance_after' => $newBalance,
                'description' => 'Stripe token top-up',
                'metadata' => [
                    'stripe_session_id' => $sessionId,
                    'amount_subtotal_minor' => $subtotalMinor,
                    'amount_major' => StripeCurrency::toMajorUnits($subtotalMinor, $currency),
                    'currency' => $currency,
                    'currency_exponent' => StripeCurrency::minorUnitExponent($currency),
                    'amount_tax_minor' => (int) ($session->total_details->amount_tax ?? 0),
                    'amount_total_minor' => (int) ($session->amount_total ?? 0),
                    'minor_units_per_token' => $minorUnitsPerToken,
                    'amd_per_token' => $this->tokenService->amdPerToken(),
                ],
            ]);

            return [
                'balance' => $newBalance,
                'tokensAdded' => $tokens,
                'alreadyCredited' => false,
            ];
        });
    }

    public function tokensFromSubtotal(int $subtotalMinorUnits, string $currency): int
    {
        $currency = strtolower(trim($currency));
        $minorUnitsPerToken = $this->minorUnitsPerToken($currency);

        if ($minorUnitsPerToken <= 0) {
            Log::warning('Token top-up: unsupported currency', [
                'currency' => $currency,
                'amount_subtotal_minor' => $subtotalMinorUnits,
            ]);

            return 0;
        }

        if ($subtotalMinorUnits < $minorUnitsPerToken) {
            return 0;
        }

        return intdiv($subtotalMinorUnits, $minorUnitsPerToken);
    }

    public function minorUnitsPerToken(string $currency): int
    {
        $currency = strtolower(trim($currency));
        $rates = config('tokens.topup_minor_units_per_token', []);

        return (int) ($rates[$currency] ?? 0);
    }

    public function currencyForCountry(string $countryCode): string
    {
        return strtoupper(trim($countryCode)) === 'AM' ? 'amd' : 'usd';
    }
}
