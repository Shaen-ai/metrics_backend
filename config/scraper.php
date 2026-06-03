<?php

return [
    /*
    |--------------------------------------------------------------------------
    | User search → scrape_urls discovery (Vega EN + Domus)
    |--------------------------------------------------------------------------
    |
    | Triggered after POST /api/marketplace/live-search for Armenia local mode.
    | Runs after the HTTP response via queued dispatch (->afterResponse()).
    |
    */

    'search_discovery' => [
        'max_pages' => max(1, min(20, (int) env('SEARCH_DISCOVERY_MAX_PAGES', 3))),
        'throttle_seconds' => max(60, (int) env('SEARCH_DISCOVERY_THROTTLE_SECONDS', 3600)),
    ],
];
