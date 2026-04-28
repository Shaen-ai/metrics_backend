<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Billing\StripePlanResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        $secret = config('stripe.webhook_secret');
        if (! is_string($secret) || $secret === '') {
            return response('Webhook not configured.', 503);
        }

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader ?? '', $secret);
        } catch (UnexpectedValueException $e) {
            return response('Invalid payload.', 400);
        } catch (SignatureVerificationException $e) {
            return response('Invalid signature.', 400);
        }

        $secretKey = config('stripe.secret');
        if (! is_string($secretKey) || $secretKey === '') {
            Log::error('Stripe webhook: STRIPE_SECRET is not set');

            return response('Stripe not configured.', 503);
        }

        if (DB::table('stripe_webhook_events')->where('id', $event->id)->exists()) {
            return response('OK', 200);
        }

        Stripe::setApiKey($secretKey);

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->handleCheckoutSessionCompleted($event->data->object),
                'customer.subscription.updated' => $this->handleSubscriptionUpdated($event->data->object),
                'customer.subscription.deleted' => $this->handleSubscriptionDeleted($event->data->object),
                'invoice.paid' => $this->handleInvoicePaid($event->data->object),
                'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event->data->object),
                default => null,
            };

            DB::table('stripe_webhook_events')->insert([
                'id' => $event->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'type' => $event->type,
                'message' => $e->getMessage(),
            ]);
            throw $e;
        }

        return response('OK', 200);
    }

    private function handleCheckoutSessionCompleted(object $session): void
    {
        if (($session->mode ?? null) !== 'subscription') {
            return;
        }

        $subscriptionId = $session->subscription ?? null;
        $customerId = $session->customer ?? null;

        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return;
        }

        $subscription = Subscription::retrieve($subscriptionId, [
            'expand' => ['items.data.price'],
        ]);

        $priceId = $subscription->items->data[0]->price->id ?? null;
        $planTier = StripePlanResolver::planTierFromPriceId($priceId);
        if ($planTier === null) {
            Log::warning('Stripe checkout: unknown price id', ['price' => $priceId]);

            return;
        }

        $user = $this->resolveUserForCheckoutSession($session);
        if ($user === null) {
            Log::notice('Stripe checkout: no user matched', [
                'client_reference_id' => $session->client_reference_id ?? null,
                'customer' => $customerId,
            ]);

            return;
        }

        $fields = array_filter([
            'plan_tier' => $planTier,
            'stripe_customer_id' => is_string($customerId) ? $customerId : $user->stripe_customer_id,
            'stripe_subscription_id' => $subscriptionId,
        ]);
        $fields['image3d_bonus_anchor_at'] = now();
        $user->update($fields);
    }

    private function handleSubscriptionUpdated(object $subscription): void
    {
        $sub = Subscription::retrieve($subscription->id, [
            'expand' => ['items.data.price'],
        ]);

        $this->applySubscriptionStateToUser($sub);
    }

    private function handleSubscriptionDeleted(object $subscription): void
    {
        $user = User::query()->where('stripe_subscription_id', $subscription->id)->first();
        if ($user === null) {
            return;
        }

        $user->update([
            'plan_tier' => 'free',
            'stripe_subscription_id' => null,
            'image3d_bonus_anchor_at' => null,
        ]);
    }

    /** Successful charge (including renewals); re-syncs plan from subscription. */
    private function handleInvoicePaid(object $invoice): void
    {
        $subscriptionId = $invoice->subscription ?? null;
        if (! is_string($subscriptionId) || $subscriptionId === '') {
            return;
        }

        $sub = Subscription::retrieve($subscriptionId, [
            'expand' => ['items.data.price'],
        ]);

        $this->applySubscriptionStateToUser($sub);
    }

    /** Payment failed (card declined, etc.); Stripe retries then may move subscription to past_due/unpaid. */
    private function handleInvoicePaymentFailed(object $invoice): void
    {
        Log::warning('Stripe invoice.payment_failed', [
            'invoice_id' => $invoice->id ?? null,
            'subscription' => $invoice->subscription ?? null,
            'customer' => $invoice->customer ?? null,
            'attempt_count' => $invoice->attempt_count ?? null,
        ]);
    }

    private function applySubscriptionStateToUser(Subscription $sub): void
    {
        $user = User::query()->where('stripe_subscription_id', $sub->id)->first();
        if ($user === null) {
            return;
        }

        $priceId = $sub->items->data[0]->price->id ?? null;
        $planTier = StripePlanResolver::planTierFromPriceId($priceId);
        $status = $sub->status ?? '';

        if (in_array($status, ['active', 'trialing', 'past_due'], true) && $planTier !== null) {
            $oldTier = $user->plan_tier;
            $payload = ['plan_tier' => $planTier];
            if ($oldTier !== $planTier) {
                $payload['image3d_bonus_anchor_at'] = now();
            }
            $user->update($payload);

            return;
        }

        if (in_array($status, ['canceled', 'unpaid', 'incomplete_expired'], true)) {
            $user->update([
                'plan_tier' => 'free',
                'stripe_subscription_id' => null,
                'image3d_bonus_anchor_at' => null,
            ]);
        }
    }

    private function resolveUserForCheckoutSession(object $session): ?User
    {
        $ref = $session->client_reference_id ?? null;
        if (is_string($ref) && $ref !== '') {
            $user = User::find($ref);
            if ($user !== null) {
                return $user;
            }
        }

        $email = $session->customer_details?->email ?? $session->customer_email ?? null;
        if (is_string($email) && $email !== '') {
            return User::query()->where('email', $email)->first();
        }

        $customerId = $session->customer ?? null;
        if (is_string($customerId) && $customerId !== '') {
            try {
                $customer = \Stripe\Customer::retrieve($customerId);
                $ce = $customer->email ?? null;
                if (is_string($ce) && $ce !== '') {
                    return User::query()->where('email', $ce)->first();
                }
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }
}
