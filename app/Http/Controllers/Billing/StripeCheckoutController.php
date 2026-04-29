<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\StripePlanResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeCheckoutController extends Controller
{
    public function redirect(Request $request): RedirectResponse|Response|JsonResponse
    {
        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            $message = 'Stripe billing is not configured. Set STRIPE_SECRET in backend/.env (test key: '
                .'https://dashboard.stripe.com/test/apikeys), then run: php artisan config:clear';

            return $request->wantsJson()
                ? response()->json(['message' => $message], 503)
                : response($message, 503);
        }

        $tier = $request->query('tier');
        $interval = $request->query('interval');

        if (! is_string($tier) || ! is_string($interval)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Invalid tier or interval.'], 422)
                : response('Invalid tier or interval.', 422);
        }

        if (! in_array($tier, ['starter', 'business', 'business-pro'], true)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Invalid tier.'], 422)
                : response('Invalid tier.', 422);
        }

        if (! in_array($interval, ['month', 'year'], true)) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Invalid interval. Use month or year.'], 422)
                : response('Invalid interval. Use month or year.', 422);
        }

        $resolved = StripePlanResolver::checkoutPriceForTier($tier, $interval);
        if ($resolved === null) {
            return $request->wantsJson()
                ? response()->json(['message' => 'Invalid price configuration for this tier.'], 503)
                : response('Invalid price configuration for this tier.', 503);
        }
        [$priceId] = $resolved;

        $user = $this->userFromBearer($request);

        $adminBase = rtrim((string) env('FRONTEND_ADMIN_URL', ''), '/');
        $landingBase = rtrim((string) env('FRONTEND_LANDING_URL', ''), '/');

        if ($adminBase === '') {
            return $request->wantsJson()
                ? response()->json(['message' => 'FRONTEND_ADMIN_URL is not configured.'], 503)
                : response('FRONTEND_ADMIN_URL is not configured.', 503);
        }
        if ($landingBase === '') {
            $landingBase = $adminBase;
        }

        if ($user !== null) {
            $successUrl = $adminBase.'/admin/settings?billing=success&session_id={CHECKOUT_SESSION_ID}';
        } else {
            $next = rawurlencode('/admin/settings');
            $successUrl = $adminBase.'/login?billing=success&session_id={CHECKOUT_SESSION_ID}&next='.$next;
        }
        $cancelUrl = $landingBase.'/pricing?billing=canceled';

        Stripe::setApiKey($secret);

        try {
            $trialDays = (int) config('stripe.subscription_trial_days', 14);

            $params = [
                'mode' => 'subscription',
                'line_items' => [
                    ['price' => $priceId, 'quantity' => 1],
                ],
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => array_filter([
                    'tier' => $tier,
                    'interval' => $interval,
                ]),
            ];

            // Deferred first charge: subscription is `trialing`; recurring price invoices after trial (card on file).
            if ($trialDays > 0) {
                $params['subscription_data'] = [
                    'trial_period_days' => $trialDays,
                ];
            }

            if ($user !== null) {
                $params['client_reference_id'] = $user->id;
                $params['metadata']['user_id'] = $user->id;
                if ($user->stripe_customer_id) {
                    $params['customer'] = $user->stripe_customer_id;
                } elseif ($user->email) {
                    $params['customer_email'] = $user->email;
                }
            }

            $session = Session::create($params);
        } catch (ApiErrorException $e) {
            report($e);

            $message = 'Could not start checkout. '.$e->getMessage();

            return $request->wantsJson()
                ? response()->json(['message' => $message], 502)
                : response($message, 502);
        }

        if ($request->wantsJson()) {
            return response()->json(['url' => $session->url]);
        }

        return redirect()->away($session->url);
    }

    private function userFromBearer(Request $request): ?User
    {
        $bearer = $request->bearerToken();
        if ($bearer === null) {
            return null;
        }

        $accessToken = PersonalAccessToken::findToken($bearer);
        $subject = $accessToken?->tokenable;

        return $subject instanceof User ? $subject : null;
    }
}
