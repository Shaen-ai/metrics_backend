<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Platform Admin (Overseer) shared secret
    |--------------------------------------------------------------------------
    | Required header secret for the platform-owner oversight panel
    | (the `overseer` app). Every /api/admin/* request must present this value
    | in the `X-Admin-Key` header IN ADDITION to a Sanctum bearer token whose
    | user has `is_platform_admin = true`.
    |
    | Leave unset (null) to hard-disable the whole admin surface — the gate
    | middleware fails closed when this is empty.
    */
    'key' => env('ADMIN_PANEL_KEY'),
];
