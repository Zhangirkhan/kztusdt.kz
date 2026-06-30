<?php

declare(strict_types=1);

return [
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', 'YourExchangeBot'),
    'login_code_ttl_minutes' => (int) env('TELEGRAM_LOGIN_CODE_TTL', 10),
    'rate_limit_per_phone' => (int) env('TELEGRAM_RATE_LIMIT_PHONE', 5),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),
];
