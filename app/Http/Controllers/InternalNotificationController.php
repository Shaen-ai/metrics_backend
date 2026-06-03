<?php

namespace App\Http\Controllers;

use App\Mail\SlotFailureNotificationMailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class InternalNotificationController extends Controller
{
    public function slotFailure(Request $request): JsonResponse
    {
        $expected = config('services.internal_api_key');
        if (! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'Internal notification API not configured.'], 503);
        }
        if ($request->header('X-Internal-Key') !== $expected) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'phase' => 'required|string|in:base,furniture,decor',
            'roomType' => 'required|string|max:120',
            'style' => 'required|string|max:120',
            'designIntent' => 'nullable|string|max:2000',
            'failedSlots' => 'required|array|min:1',
            'failedSlots.*.family' => 'required|string|max:80',
            'failedSlots.*.subtype' => 'nullable|string|max:80',
            'failedSlots.*.label' => 'required|string|max:120',
        ]);

        $recipient = config('mail.contact_inbound.address', 'support@tunzone.com');

        Mail::to($recipient)->send(new SlotFailureNotificationMailable(
            phase: $data['phase'],
            roomType: $data['roomType'],
            style: $data['style'],
            failedSlots: $data['failedSlots'],
            designIntent: $data['designIntent'] ?? null,
        ));

        return response()->json(['ok' => true]);
    }
}
