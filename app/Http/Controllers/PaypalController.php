<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Notifications\OrderPaidNotification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PaypalController extends Controller
{
    public function ipn(Request $request): JsonResponse
    {
        Log::info('PayPal IPN received', $request->all());

        $postData = 'cmd=_notify-validate&' . http_build_query($request->all());

        $paypalUrl = config('services.paypal.sandbox', false)
            ? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
            : 'https://ipnpb.paypal.com/cgi-bin/webscr';

        try {
            $response = Http::asForm()
                ->timeout(30)
                ->withBody($postData, 'application/x-www-form-urlencoded')
                ->post($paypalUrl);

            $body = $response->body();
            Log::info('PayPal IPN verification response', ['body' => $body]);

            if (trim($body) !== 'VERIFIED') {
                Log::warning('PayPal IPN not verified', ['body' => $body]);
                return response()->json(['status' => 'invalid'], 400);
            }
        } catch (\Exception $e) {
            Log::error('PayPal IPN verification failed', ['error' => $e->getMessage()]);
            return response()->json(['status' => 'error'], 500);
        }

        $paymentStatus = $request->input('payment_status');
        $orderId = $request->input('custom');
        $txnId = $request->input('txn_id');

        if ($paymentStatus !== 'Completed') {
            Log::info('PayPal IPN: payment not completed', [
                'status' => $paymentStatus,
                'order_id' => $orderId,
            ]);
            return response()->json(['status' => 'noted']);
        }

        $order = Order::find($orderId);
        if (!$order) {
            Log::warning('PayPal IPN: order not found', ['order_id' => $orderId]);
            return response()->json(['status' => 'order_not_found'], 404);
        }

        if ($order->payment_status === 'paid') {
            Log::info('PayPal IPN: order already marked as paid', ['order_id' => $orderId]);
            return response()->json(['status' => 'already_processed']);
        }

        $order->update([
            'payment_status' => 'paid',
            'paypal_transaction_id' => $txnId,
            'status' => 'confirmed',
        ]);

        Log::info('PayPal IPN: order marked as paid', [
            'order_id' => $orderId,
            'txn_id' => $txnId,
        ]);

        $admin = $order->admin;
        if ($admin) {
            $admin->notify(new OrderPaidNotification($order));
            Log::info('Admin notification sent for paid order', [
                'order_id' => $orderId,
                'admin_email' => $admin->email,
            ]);
        }

        return response()->json(['status' => 'ok']);
    }
}
