<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'YourExchangeBot'),
    'login_code_ttl_minutes' => (int) env('TELEGRAM_LOGIN_CODE_TTL', 10),
    'rate_limit_per_phone' => (int) env('TELEGRAM_RATE_LIMIT_PHONE', 5),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
     | Telegram Gateway API (gatewayapi.telegram.org) — delivers one-time
     | verification codes straight to a phone number's Telegram account,
     | without requiring the user to interact with a bot.
     */
    'gateway' => [
        'token' => env('TELEGRAM_GATEWAY_TOKEN'),
        'base_url' => env('TELEGRAM_GATEWAY_BASE_URL', 'https://gatewayapi.telegram.org'),
        'sender_username' => env('TELEGRAM_GATEWAY_SENDER_USERNAME'),
        'code_length' => (int) env('TELEGRAM_GATEWAY_CODE_LENGTH', 6),
        'code_ttl_seconds' => (int) env('TELEGRAM_GATEWAY_CODE_TTL', 300),
        'max_attempts' => (int) env('TELEGRAM_GATEWAY_MAX_ATTEMPTS', 5),
    ],
];
