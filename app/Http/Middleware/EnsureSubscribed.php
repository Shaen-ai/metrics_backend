<?php

namespace App\Http\Middleware;

use App\Support\PlanEntitlements;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscribed
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! PlanEntitlements::hasActiveSubscription($user)) {
            return response()->json([
                'message' => 'An active subscription is required to use this feature.',
            ], 403);
        }

        return $next($request);
    }
}
