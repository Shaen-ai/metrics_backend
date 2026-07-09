<?php

namespace App\Http\Controllers;

use App\Mail\SlotFailureNotificationMailable;
use App\Mail\VistaIssueNotificationMailable;
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

    public function vistaIssue(Request $request): JsonResponse
    {
        $expected = config('services.internal_api_key');
        if (! is_string($expected) || $expected === '') {
            return response()->json(['message' => 'Internal notification API not configured.'], 503);
        }
        if ($request->header('X-Internal-Key') !== $expected) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $data = $request->validate([
            'category' => 'required|string|in:provider_auth,unexpected',
            'provider' => 'nullable|string|in:fal,openai,anthropic,gemini,unknown',
            'route' => 'required|string|max:200',
            'errorMessage' => 'required|string|max:5000',
            'roomType' => 'nullable|string|max:120',
            'phase' => 'nullable|string|max:80',
            'occurredAt' => 'nullable|string|max:40',
        ]);

        $recipient = config('mail.contact_inbound.address', 'support@tunzone.com');

        Mail::to($recipient)->send(new VistaIssueNotificationMailable(
            category: $data['category'],
            provider: $data['provider'] ?? null,
            route: $data['route'],
            errorMessage: $data['errorMessage'],
            roomType: $data['roomType'] ?? null,
            phase: $data['phase'] ?? null,
            occurredAt: $data['occurredAt'] ?? null,
        ));

        return response()->json(['ok' => true]);
    }
}
