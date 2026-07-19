<?php

use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureSubscribed;
use App\Http\Middleware\SilentRegisterRateLimit;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Scrape each marketplace nightly — discovers new products, updates prices/stock,
        // marks removed products unavailable. Staggered so they don't overlap.
        // withoutOverlapping() prevents a slow scrape from spawning a second instance.
        $schedule->command('scrape:sync vega --sync-qdrant --delete-unavailable')
            ->dailyAt('00:00')->withoutOverlapping()->runInBackground();
        $schedule->command('scrape:sync domus --sync-qdrant --delete-unavailable')
            ->dailyAt('01:00')->withoutOverlapping()->runInBackground();
        $schedule->command('scrape:sync jysk --sync-qdrant --delete-unavailable')
            ->dailyAt('01:30')->withoutOverlapping()->runInBackground();

        // Classify any newly scraped products, enrich, embed, push to Qdrant
        $schedule->command('catalog:backfill-taxonomy --only-missing')->dailyAt('02:00');
        $schedule->command('catalog:audit-taxonomy')->dailyAt('02:05');
        $schedule->command('catalog:enrich-ai-tags --only-missing --rebuild-text')->dailyAt('02:10');
        $schedule->command('catalog:build-embedding-text')->dailyAt('02:30');
        $schedule->command('catalog:embed-qdrant --only-missing')->dailyAt('02:40');

        // Weekly: fix any taxonomy drift accumulated during the week, then re-embed
        $schedule->command('catalog:backfill-taxonomy --fix-drift')->weeklyOn(0, '03:00');
        $schedule->command('catalog:build-embedding-text --only-stale')->weeklyOn(0, '03:10');
        $schedule->command('catalog:embed-qdrant --only-stale')->weeklyOn(0, '03:20');
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn () => null);
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );
        $middleware->alias([
            'subscribed' => EnsureSubscribed::class,
            'silent.register.limit' => SilentRegisterRateLimit::class,
            'platform-admin' => EnsurePlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthenticationException $e, $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
        });
    })->create();
