<?php

namespace App\Http\Controllers;

use App\Mail\FeatureRequestMailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class FeatureRequestController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string', 'min:10', 'max:3000'],
            'email' => ['nullable', 'email', 'max:255'],
            'page' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        // Hidden field catches simple form bots without revealing whether mail was sent.
        if (! empty($data['website'])) {
            return response()->json(['ok' => true]);
        }

        $to = config('mail.contact_inbound.address');
        if (! is_string($to) || trim($to) === '') {
            Log::error('Feature request: contact_inbound address is not configured');

            return response()->json(['message' => 'Suggestions are temporarily unavailable.'], 503);
        }

        try {
            Mail::to($to)->send(new FeatureRequestMailable(
                trim($data['description']),
                isset($data['email']) && $data['email'] !== '' ? $data['email'] : null,
                $data['page'] ?? null,
            ));
        } catch (Throwable $e) {
            Log::error('Feature request mail failed', ['exception' => $e->getMessage()]);

            return response()->json(['message' => 'We could not send your suggestion. Please try again later.'], 503);
        }

        return response()->json(['ok' => true]);
    }
}
