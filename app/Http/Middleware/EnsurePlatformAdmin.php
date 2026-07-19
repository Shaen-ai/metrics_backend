<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the platform-owner oversight panel (`overseer`).
 *
 * Defense in depth — BOTH must pass or the request is rejected as 404 (not 403),
 * so the whole admin surface is undiscoverable to anyone without the secret:
 *   1. An authenticated Sanctum user with `is_platform_admin = true`.
 *   2. An `X-Admin-Key` header matching config('admin.key') (constant-time compare).
 *
 * This gate resolves the Sanctum user itself (rather than relying on the
 * `auth:sanctum` middleware) so it can be the FIRST thing that runs — Laravel's
 * middleware-priority list would otherwise pull `auth:sanctum` ahead of it and
 * leak a 401 (revealing the route) for unauthenticated requests. Doing the auth
 * here keeps every unauthorized case a uniform, undiscoverable 404. On success
 * it binds the user resolver so downstream `$request->user()` works as usual.
 *
 * Fails closed: if `ADMIN_PANEL_KEY` is unset the surface is fully disabled.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('admin.key', '');
        $provided = (string) $request->header('X-Admin-Key', '');
        $user = $request->user('sanctum');

        $ok = $configured !== ''
            && $provided !== ''
            && hash_equals($configured, $provided)
            && $user !== null
            && (bool) ($user->is_platform_admin ?? false) === true;

        if (! $ok) {
            // Undiscoverable: behave exactly like an unknown route.
            abort(404);
        }

        // Bind for the default guard so controllers/$request->user() resolve it.
        auth()->setUser($user);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
