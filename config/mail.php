<?php

return [
    'default' => env('MAIL_MAILER', 'log'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@tunzone.com'),
        'name' => env('MAIL_FROM_NAME', 'Mebel'),
    ],

    'mailers' => [
        'log' => [
            'transport' => 'log',
        ],
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 25),
            'encryption' => in_array(env('MAIL_ENCRYPTION'), [null, '', 'null'], true) ? null : env('MAIL_ENCRYPTION'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
        ],
        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs'),
        ],
    ],
];
