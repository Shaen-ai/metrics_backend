<?php

return [
    'name' => env('APP_NAME', 'MebelBackend'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost'),
    'timezone' => 'UTC',
    'locale' => 'en',
    'fallback_locale' => 'en',
    'faker_locale' => 'en_US',
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],
    'maintenance' => [
        'driver' => 'file',
    ],
    'frontend_admin_url' => env('FRONTEND_ADMIN_URL', 'http://localhost:3000'),
    'frontend_public_url' => env('FRONTEND_PUBLIC_URL', 'http://localhost:3001'),
    /**
     * Public catalog in planners is merged with this user’s active catalog items
     * when the viewer’s admin has use_custom_planner_catalog = false.
     */
    'catalog_library_slug' => env('CATALOG_LIBRARY_SLUG', 'demo'),
];
