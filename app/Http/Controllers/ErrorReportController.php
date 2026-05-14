<?php

namespace App\Http\Controllers;

use App\Mail\ErrorReportMailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ErrorReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'screenshot' => ['nullable', 'string', 'max:5000000'],
            'url' => ['nullable', 'string', 'max:500'],
            'userAgent' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();

        try {
            Mail::to('support@tunzone.com')->send(new ErrorReportMailable(
                userId: (string) $user->id,
                userEmail: $user->email,
                errorMessage: $data['message'],
                screenshot: $data['screenshot'] ?? null,
                url: $data['url'] ?? null,
                userAgent: $data['userAgent'] ?? null,
            ));
        } catch (Throwable $e) {
            Log::error('Error report mail failed', ['exception' => $e->getMessage()]);
        }

        return response()->json(['ok' => true]);
    }
}
