<?php

return [
    'costs' => [
        'generate' => 10,
        'regenerate' => 5,
        'edit' => 3,
    ],
    'anonymous_grant' => (int) env('TOKENS_ANONYMOUS_GRANT', 20),
    'login_bonus' => (int) env('TOKENS_LOGIN_BONUS', 0),
    'referral_invitee_bonus' => 0,
    'referral_referrer_bonus' => 20,
    'referral_earnings_cap' => 200,
    'amd_per_token' => 40,

    /** Stripe minor units per token for top-up crediting (4000 = 40.00 AMD, 10 = $0.10). */
    'topup_minor_units_per_token' => [
        'amd' => 4000,
        'usd' => 10,
    ],
];
