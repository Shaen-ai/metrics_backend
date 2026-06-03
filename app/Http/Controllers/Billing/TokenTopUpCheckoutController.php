<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Tokens\TokenTopUpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Product;
use Stripe\Stripe;

class TokenTopUpCheckoutController extends Controller
{
    public function __construct(
        private readonly TokenTopUpService $tokenTopUpService,
    ) {}

    public function create(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Sign in to top up your token balance.'], 401);
        }

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json([
                'message' => 'Stripe billing is not configured. Set STRIPE_SECRET in backend/.env.',
            ], 503);
        }

        $productId = trim((string) config('stripe.token_topup_product_id'));
        if ($productId === '') {
            return response()->json([
                'message' => 'Token top-up is not configured. Set STRIPE_PRODUCT_TOKEN_TOPUP in backend/.env.',
            ], 503);
        }

        $vistaBase = rtrim((string) config('app.frontend_vista_url'), '/');
        if ($vistaBase === '') {
            return response()->json(['message' => 'FRONTEND_VISTA_URL is not configured.'], 503);
        }

        Stripe::setApiKey($secret);

        try {
            $lineItems = $this->lineItemsForProduct($productId);

            $params = [
                'mode' => 'payment',
                'line_items' => $lineItems,
                'success_url' => $vistaBase.'/?topup=success&session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $vistaBase.'/?topup=canceled',
                'client_reference_id' => $user->id,
                'metadata' => [
                    'purpose' => 'token_topup',
                    'user_id' => $user->id,
                ],
                'automatic_tax' => ['enabled' => true],
                'billing_address_collection' => 'required',
                'locale' => $this->checkoutLocale($request),
            ];

            if ($user->stripe_customer_id) {
                $params['customer'] = $user->stripe_customer_id;
                $params['customer_update'] = ['address' => 'auto'];
            } elseif ($user->email) {
                $params['customer_email'] = $user->email;
            }

            $session = Session::create($params);
        } catch (ApiErrorException $e) {
            report($e);

            return response()->json([
                'message' => 'Could not start checkout. '.$e->getMessage(),
            ], 502);
        }

        return response()->json(['url' => $session->url]);
    }

    public function verify(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Sign in required.'], 401);
        }

        $sessionId = $request->query('session_id');
        if (! is_string($sessionId) || trim($sessionId) === '') {
            return response()->json(['message' => 'session_id is required.'], 422);
        }

        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json(['message' => 'Stripe is not configured.'], 503);
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::retrieve($sessionId);
        } catch (ApiErrorException $e) {
            report($e);

            return response()->json(['message' => 'Could not verify payment.'], 502);
        }

        if (($session->payment_status ?? '') !== 'paid') {
            return response()->json(['message' => 'Payment is not completed yet.'], 409);
        }

        if (($session->metadata->purpose ?? null) !== 'token_topup') {
            return response()->json(['message' => 'Invalid checkout session.'], 422);
        }

        $sessionUserId = $session->metadata->user_id ?? $session->client_reference_id ?? null;
        if ((string) $sessionUserId !== (string) $user->id) {
            return response()->json(['message' => 'This payment belongs to another account.'], 403);
        }

        $result = $this->tokenTopUpService->creditFromCheckoutSession($session, $user);

        return response()->json(['data' => $result]);
    }

    private function checkoutLocale(Request $request): string
    {
        $allowed = [
            'auto', 'bg', 'cs', 'da', 'de', 'el', 'en', 'en-GB', 'es', 'es-419', 'et', 'fi', 'fil', 'fr', 'fr-CA',
            'hr', 'hu', 'id', 'it', 'ja', 'ko', 'lt', 'lv', 'ms', 'mt', 'nb', 'nl', 'pl', 'pt', 'pt-BR', 'ro', 'ru',
            'sk', 'sl', 'sv', 'th', 'tr', 'vi', 'zh', 'zh-HK', 'zh-TW',
        ];

        $fromConfig = trim((string) config('stripe.vista_checkout_locale', 'auto'));
        if ($fromConfig !== 'auto' && in_array($fromConfig, $allowed, true)) {
            return $fromConfig;
        }

        $fromBody = $request->input('locale');
        if (is_string($fromBody)) {
            $candidate = trim($fromBody);
            if ($candidate !== 'auto' && in_array($candidate, $allowed, true)) {
                return $candidate;
            }
        }

        $fromAccept = $this->localeFromAcceptLanguage($request);
        if ($fromAccept !== null) {
            return $fromAccept;
        }

        return 'auto';
    }

    private function localeFromAcceptLanguage(Request $request): ?string
    {
        $header = strtolower((string) $request->header('Accept-Language', ''));
        if ($header === '') {
            return null;
        }

        // Stripe Checkout does not support Armenian (hy); Russian is the closest for AM users.
        if (preg_match('/\b(hy|ru)\b/', $header)) {
            return 'ru';
        }

        return null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineItemsForProduct(string $productId): array
    {
        $currency = (string) config('stripe.token_topup_currency', 'amd');
        $amdPerToken = (int) config('tokens.amd_per_token', 40);
        $minimum = max($amdPerToken * 10, $amdPerToken);
        $preset = max($amdPerToken * 100, $minimum);

        try {
            $product = Product::retrieve($productId, ['expand' => ['default_price']]);
            $defaultPrice = $product->default_price ?? null;
            $priceId = is_object($defaultPrice)
                ? ($defaultPrice->id ?? null)
                : (is_string($defaultPrice) ? $defaultPrice : null);

            if (is_string($priceId) && $priceId !== '') {
                return [['price' => $priceId, 'quantity' => 1]];
            }
        } catch (ApiErrorException $e) {
            report($e);
        }

        return [[
            'price_data' => [
                'currency' => $currency,
                'product' => $productId,
                'custom_unit_amount' => [
                    'enabled' => true,
                    'minimum' => $minimum,
                    'preset' => $preset,
                ],
            ],
            'quantity' => 1,
        ]];
    }
}
