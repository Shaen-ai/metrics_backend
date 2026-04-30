<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:3001',
        'http://localhost:3002',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:3001',
        'http://127.0.0.1:3002',
        'https://tunzone.com',
        'https://www.tunzone.com',
        'https://published.tunzone.com',
        'https://admin.tunzone.com',
        'https://api.tunzone.com',
    ],

    'allowed_origins_patterns' => [
        '#^https://[a-z0-9-]+\.tunzone\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
