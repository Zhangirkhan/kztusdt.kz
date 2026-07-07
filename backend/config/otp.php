<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp OTP API (otp.kztusdt.kz)
    |--------------------------------------------------------------------------
    |
    | Delivers one-time login codes via WhatsApp. Token is issued in the OTP
    | service admin panel: https://otp.kztusdt.kz/admin
    |
    */
    'base_url' => env('OTP_API_BASE_URL', 'https://otp.kztusdt.kz/api'),
    'token' => env('OTP_API_TOKEN'),
    'purpose' => env('OTP_API_PURPOSE', 'login'),
    'code_length' => (int) env('OTP_CODE_LENGTH', 6),
    'code_ttl_seconds' => (int) env('OTP_CODE_TTL', 300),
    'max_attempts' => (int) env('OTP_MAX_ATTEMPTS', 5),
    'resend_cooldown_seconds' => (int) env('OTP_RESEND_COOLDOWN', 60),
    'rate_limit_per_phone' => (int) env('OTP_RATE_LIMIT_PHONE', env('TELEGRAM_RATE_LIMIT_PHONE', 5)),
];
