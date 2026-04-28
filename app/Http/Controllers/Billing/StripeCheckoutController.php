<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\StripePlanResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Sanctum\PersonalAccessToken;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripeCheckoutController extends Controller
{
    public function redirect(Request $request): RedirectResponse|Response
    {
        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response(
                'Stripe billing is not configured. Set STRIPE_SECRET in backend/.env (test key: '
                .'https://dashboard.stripe.com/test/apikeys), then run: php artisan config:clear',
                503,
            );
        }

        $tier = $request->query('tier');
        $interval = $request->query('interval');

        if (! is_string($tier) || ! is_string($interval)) {
            return response('Invalid tier or interval.', 422);
        }

        if (! in_array($tier, ['starter', 'business', 'business-pro'], true)) {
            return response('Invalid tier.', 422);
        }

        if (! in_array($interval, ['month', 'year'], true)) {
            return response('Invalid interval. Use month or year.', 422);
        }

        $resolved = StripePlanResolver::checkoutPriceForTier($tier, $interval);
        if ($resolved === null) {
            return response('Invalid price configuration for this tier.', 503);
        }
        [$priceId] = $resolved;

        $user = $this->userFromBearer($request);

        $adminBase = rtrim((string) env('FRONTEND_ADMIN_URL', ''), '/');
        $landingBase = rtrim((string) env('FRONTEND_LANDING_URL', ''), '/');

        if ($adminBase === '') {
            return response('FRONTEND_ADMIN_URL is not configured.', 503);
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

            return response('Could not start checkout. '.$e->getMessage(), 502);
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
