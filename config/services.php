<?php

return [
    'paypal' => [
        'sandbox' => env('PAYPAL_SANDBOX', true),
    ],

    /** Shared secret for Next.js server → Laravel usage metering (storefront AI routes). */
    'internal_api_key' => env('INTERNAL_API_KEY', ''),
];
