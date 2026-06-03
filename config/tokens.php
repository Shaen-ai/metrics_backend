<?php

return [
    'costs' => [
        'generate' => 10,
        'regenerate' => 5,
        'edit' => 3,
    ],
    // Temporary test default — override with TOKENS_ANONYMOUS_GRANT=20 before public launch.
    'anonymous_grant' => (int) env('TOKENS_ANONYMOUS_GRANT', 1000),
    'login_bonus' => 20,
    'referral_invitee_bonus' => 40,
    'referral_referrer_bonus' => 20,
    'referral_earnings_cap' => 200,
    'amd_per_token' => 40,
];
