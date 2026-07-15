<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SilentRegisterRateLimit
{
    private const SUCCESS_MESSAGE = 'Check your email for a link to verify your account before signing in.';

    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?: 'unknown';
        $key = 'register:rate:'.$ip;

        if (Cache::has($key)) {
            Log::info('Register rate-limited', ['ip' => $ip]);

            return response()->json([
                'message' => self::SUCCESS_MESSAGE,
            ], 201);
        }

        Cache::put($key, 1, now()->addMinute());

        return $next($request);
    }
}
