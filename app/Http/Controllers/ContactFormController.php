<?php

namespace App\Http\Controllers;

use App\Mail\ContactFormMailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ContactFormController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $to = config('mail.contact_inbound.address');
        if (! is_string($to) || trim($to) === '') {
            Log::error('Contact form: mail.contact_inbound.address is not configured');

            return response()->json([
                'message' => 'Contact is temporarily unavailable.',
            ], 503);
        }

        try {
            Mail::to($to)->send(new ContactFormMailable(
                $data['name'],
                $data['email'],
                $data['message'],
            ));
        } catch (Throwable $e) {
            Log::error('Contact form mail failed', [
                'exception' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'We could not send your message. Please try again later.',
            ], 503);
        }

        return response()->json(['ok' => true]);
    }
}
