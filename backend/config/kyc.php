<?php

declare(strict_types=1);

return [
    // manual — internal review by security officer;
    // sumsub — external provider WebSDK;
    // aitu   — identity verification via Aitu Passport (result delivered in the id_token).
    'provider' => env('KYC_PROVIDER', 'manual'),

    /*
    | Ручная подача документов (анкета + фото). При KYC_PROVIDER=aitu/sumsub
    | форма скрыта по умолчанию; включается для конкретного клиента в админке
    | (users.manual_kyc_enabled) или глобально через KYC_MANUAL_ENABLED=true.
    */
    'manual_enabled' => (bool) env('KYC_MANUAL_ENABLED', false),

    /*
    | Показывать заявки Sumsub в /admin/kyc. Код Sumsub остаётся в проекте;
    | включите позже: KYC_ADMIN_SUMSUB_ENABLED=true
    */
    'admin_show_sumsub' => (bool) env('KYC_ADMIN_SUMSUB_ENABLED', false),

    'sumsub' => [
        'base_url' => env('SUMSUB_BASE_URL', 'https://api.sumsub.com'),
        'app_token' => env('SUMSUB_APP_TOKEN', ''),
        'secret_key' => env('SUMSUB_SECRET_KEY', ''),
        'level_name' => env('SUMSUB_LEVEL_NAME', 'id-and-liveness'),
        'webhook_secret' => env('SUMSUB_WEBHOOK_SECRET', ''),
        'access_token_ttl' => (int) env('SUMSUB_ACCESS_TOKEN_TTL', 600),
        'dashboard_url' => env('SUMSUB_DASHBOARD_URL', 'https://cockpit.sumsub.com/checkus/applicant'),
    ],
];
