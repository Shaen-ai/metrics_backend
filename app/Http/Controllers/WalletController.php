<?php

namespace App\Http\Controllers;

use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Stripe;

class WalletController extends Controller
{
    public function __construct(private WalletService $walletService) {}

    /**
     * GET /api/wallet/balance
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'data' => [
                'balance_usd' => $this->walletService->getBalance($user),
                'cost_per_design_usd' => WalletService::INTERIOR_DESIGN_COST_USD,
                'designs_remaining' => floor($this->walletService->getBalance($user) / WalletService::INTERIOR_DESIGN_COST_USD),
            ],
        ]);
    }

    /**
     * GET /api/wallet/transactions
     */
    public function transactions(Request $request): JsonResponse
    {
        $user = $request->user();
        $transactions = $this->walletService->getTransactionHistory($user);

        return response()->json(['data' => $transactions]);
    }

    /**
     * POST /api/wallet/top-up
     * Create a Stripe Checkout session for wallet top-up.
     */
    public function topUp(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount_usd' => 'required|numeric|min:1|max:500',
        ]);

        $user = $request->user();
        $amount = (float) $data['amount_usd'];

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => 'Stripe is not configured.'], 503);
        }

        Stripe::setApiKey($secret);

        $adminBase = rtrim((string) config('app.frontend_admin_url'), '/');
        $consumerBase = rtrim((string) config('app.frontend_consumer_url', $adminBase), '/');

        try {
            $session = Session::create([
                'mode' => 'payment',
                'line_items' => [[
                    'price_data' => [
                        'currency' => 'usd',
                        'product_data' => [
                            'name' => 'Wallet Top-Up',
                            'description' => "Add \${$amount} to your design credit balance",
                        ],
                        'unit_amount' => (int) ($amount * 100),
                    ],
                    'quantity' => 1,
                ]],
                'client_reference_id' => $user->id,
                'customer_email' => $user->email,
                'customer' => $user->stripe_customer_id ?: null,
                'success_url' => $consumerBase.'/design?topup=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $consumerBase.'/design?topup=canceled',
                'metadata' => [
                    'type' => 'wallet_top_up',
                    'user_id' => $user->id,
                    'amount_usd' => $amount,
                ],
            ]);

            return response()->json([
                'data' => [
                    'checkout_url' => $session->url,
                    'session_id' => $session->id,
                ],
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json(['message' => 'Could not create checkout session.'], 502);
        }
    }
}
