<?php

return [
    'paypal' => [
        'sandbox' => env('PAYPAL_SANDBOX', true),
    ],

    /** Shared secret for Next.js server → Laravel usage metering (storefront AI routes). */
    'internal_api_key' => env('INTERNAL_API_KEY', ''),

    /** Absolute server directory for uploaded GLB/GLTF models. Empty = Laravel public disk. */
    'model_upload_path' => env('MODEL_UPLOAD_PATH'),

    /** Public URL path that Nginx aliases to model_upload_path. */
    'model_upload_url_path' => env('MODEL_UPLOAD_URL_PATH', '/files/models'),
];
