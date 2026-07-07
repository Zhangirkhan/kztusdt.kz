<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | NCANode HTTP API (v3)
    |--------------------------------------------------------------------------
    |
    | Сервер проверки ЭЦП НУЦ РК. Локально: docker compose up ncanode
    |
    */

    'base_url' => rtrim((string) env('NCANODE_BASE_URL', 'http://127.0.0.1:14579'), '/'),

    'timeout' => (int) env('NCANODE_TIMEOUT', 30),

    'verify_ocsp' => (bool) env('NCANODE_VERIFY_OCSP', true),

    'verify_crl' => (bool) env('NCANODE_VERIFY_CRL', true),

    /*
    |--------------------------------------------------------------------------
    | Регистрация юр. лиц
    |--------------------------------------------------------------------------
    */

    'legal_entity_eds_required' => (bool) env('LEGAL_ENTITY_EDS_REQUIRED', true),

    'challenge_ttl_seconds' => (int) env('LEGAL_ENTITY_EDS_CHALLENGE_TTL', 600),

    /*
    | Только для локальной разработки без NCANode — НЕ включать в production.
    */
    'skip_verification' => (bool) env('NCANODE_SKIP_VERIFICATION', false),

];
