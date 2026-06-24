<?php

declare(strict_types=1);

/**
 * Web Push (PWA notifications) configuration.
 *
 * Replaces the Telegram bot for user-facing notifications. VAPID keys identify
 * this application server to the browser push services. Generate a pair with
 * `php artisan webpush:vapid` and store them in the environment.
 */
return [
    'vapid' => [
        // mailto: or https: URI identifying the sender to push services.
        'subject' => env('VAPID_SUBJECT', env('APP_URL', 'https://kztusdt.kz')),
        'public_key' => env('VAPID_PUBLIC_KEY', ''),
        'private_key' => env('VAPID_PRIVATE_KEY', ''),
    ],

    // How long (seconds) a push service should retain an undelivered message.
    'ttl' => (int) env('PUSH_TTL', 2_419_200), // 4 weeks

    // Default landing path opened when the user taps a notification.
    'default_url' => env('PUSH_DEFAULT_URL', '/home'),
];
