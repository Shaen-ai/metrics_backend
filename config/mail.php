<?php

use App\Support\MailBranding;

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
        /*
         * Customer-facing From name. Sanitized so internal names containing "mebel" never appear.
         */
        'name' => MailBranding::configuredFromDisplayName(),
    ],

    /*
    | Logo shown at the top of HTML admin emails. Absolute URL required for mail clients.
    | Default: FRONTEND_LANDING_URL + /logo.png (see landing public logo).
    */
    'brand_logo_url' => (static function () {
        $explicit = env('MAIL_BRAND_LOGO_URL');
        if (is_string($explicit) && trim($explicit) !== '') {
            return trim($explicit);
        }
        $base = rtrim((string) env('FRONTEND_LANDING_URL', ''), '/');

        return $base !== '' ? $base.'/logo.png' : null;
    })(),

    'reply_to' => [
        'address' => env('MAIL_REPLY_TO_ADDRESS'),
        'name' => MailBranding::configuredReplyToDisplayName(),
    ],

    /*
    | Landing / marketing contact form (about page). Must be a real inbox.
    */
    'contact_inbound' => [
        'address' => env('CONTACT_INBOUND_EMAIL', 'support@tunzone.com'),
    ],

    'markdown' => [
        'theme' => env('MAIL_MARKDOWN_THEME', 'default'),

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
