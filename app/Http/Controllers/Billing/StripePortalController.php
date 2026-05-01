<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\BillingPortal\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class StripePortalController extends Controller
{
    public function create(Request $request): JsonResponse
    {
        $secret = config('stripe.secret');
        if (! is_string($secret) || $secret === '') {
            return response()->json([
                'message' => 'Stripe billing is not configured.',
            ], 503);
        }

        $user = $request->user();
        if ($user === null || ! is_string($user->stripe_customer_id) || $user->stripe_customer_id === '') {
            return response()->json([
                'message' => 'No Stripe customer is linked to this account. Contact billing support to manage this subscription.',
            ], 422);
        }

        $adminBase = rtrim((string) config('app.frontend_admin_url'), '/');
        if ($adminBase === '') {
            return response()->json([
                'message' => 'FRONTEND_ADMIN_URL is not configured.',
            ], 503);
        }

        Stripe::setApiKey($secret);

        try {
            $session = Session::create([
                'customer' => $user->stripe_customer_id,
                'return_url' => $adminBase.'/admin/settings?tab=billing',
            ]);
        } catch (ApiErrorException $e) {
            report($e);

            return response()->json([
                'message' => 'Could not open the billing portal. '.$e->getMessage(),
            ], 502);
        }

        return response()->json(['url' => $session->url]);
    }
}
