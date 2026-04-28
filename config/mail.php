<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | All application mail (verification, notifications, etc.) uses this
    | mailer unless another is specified when sending.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', 'smtp.zoho.com'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url(env('API_PUBLIC_URL', env('APP_URL', 'http://localhost')), PHP_URL_HOST) ?: 'localhost'),
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@tunzone.com'),
        /*
         * PHP does not expand "${APP_NAME}" from .env; treat that literal as "use APP_NAME".
         */
        'name' => (static function () {
            $fallback = env('APP_NAME', 'Tunzone');
            $raw = env('MAIL_FROM_NAME');
            if ($raw === null || $raw === '') {
                return $fallback;
            }
            $trimmed = trim($raw, " \t\n\r\0\x0B\"'");
            if ($trimmed === '${APP_NAME}') {
                return $fallback;
            }

            return $raw;
        })(),
    ],

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS'),
        'name' => env('MAIL_REPLY_TO_NAME'),
    ],

    'markdown' => [
        'theme' => env('MAIL_MARKDOWN_THEME', 'default'),

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
