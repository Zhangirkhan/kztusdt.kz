<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Aitu Passport (OAuth 2.0 / OpenID Connect)
    |--------------------------------------------------------------------------
    |
    | Aitu Passport — единая аутентификация по протоколу OAuth 2.0 / OpenID.
    | client_id / client_secret выдаются в клиентской консоли после создания
    | сервиса Партнёра. Документация: https://docs.aitu.io/aituapps/aitu-passport
    |
    | Эндпоинты вынесены в env, так как пути отличаются на тестовой и боевой
    | площадках Aitu Passport. Значения по умолчанию соответствуют прод-хосту.
    |
    */

    'client_id' => env('AITU_CLIENT_ID', ''),

    'client_secret' => env('AITU_CLIENT_SECRET', ''),

    // Базовый хост Aitu Passport (без завершающего слэша).
    'base_url' => rtrim((string) env('AITU_BASE_URL', 'https://passport.aitu.io'), '/'),

    // Пути эндпоинтов относительно base_url (можно переопределить в .env).
    'endpoints' => [
        'authorize' => env('AITU_AUTHORIZE_PATH', '/oauth2/auth'),
        'token' => env('AITU_TOKEN_PATH', '/api/v1/oauth/token'),
        'logout' => env('AITU_LOGOUT_PATH', '/sessions/logout'),
    ],

    /*
     | scope — список сервисов Aitu Passport, разделённый пробелом.
     | openid обязателен для получения id_token. Остальные scope подключаются
     | по подписке через проектного менеджера Aitu Passport.
     */
    'scope' => env('AITU_SCOPE', 'openid phone'),

    'locale' => env('AITU_LOCALE', 'ru'),

    // HTTP-таймаут для серверных запросов к token-эндпоинту (секунды).
    'http_timeout' => (int) env('AITU_HTTP_TIMEOUT', 15),

    /*
     | Секрет для проверки вебхука Logout Callback URI (если Aitu Passport
     | подписывает уведомление). Оставьте пустым, если подпись не используется.
     */
    'logout_webhook_secret' => env('AITU_LOGOUT_WEBHOOK_SECRET', ''),

    /*
     | Проверка подписи и claims id_token (OpenID Connect).
     |
     | id_token приходит серверным обменом по TLS с client_secret, но мы всё равно
     | валидируем exp / iss / aud, а при наличии ключа — RSA-подпись (RS256), и
     | действуем fail-closed: если ключ задан и подпись неверна — вход отклоняется.
     |
     | public_key / public_key_path — PEM открытого ключа Aitu (если выдан напрямую).
     | jwks_uri                      — JWKS-эндпоинт; ключ выбирается по `kid`.
     | issuer                        — ожидаемый iss (пусто = не проверять).
     | verify_audience               — проверять, что aud содержит client_id.
     | leeway                        — допустимый сдвиг часов в секундах.
     | jwks_cache_ttl                — сколько секунд кэшировать JWKS.
     */
    'id_token' => [
        'public_key' => env('AITU_ID_TOKEN_PUBLIC_KEY'),
        'public_key_path' => env('AITU_ID_TOKEN_PUBLIC_KEY_PATH'),
        'jwks_uri' => env('AITU_JWKS_URI'),
        'issuer' => env('AITU_ID_TOKEN_ISSUER'),
        'verify_audience' => (bool) env('AITU_ID_TOKEN_VERIFY_AUD', false),
        'leeway' => (int) env('AITU_ID_TOKEN_LEEWAY', 60),
        'jwks_cache_ttl' => (int) env('AITU_JWKS_CACHE_TTL', 3600),
    ],

    /*
     | Подпись ИИН (oauth-параметр iin_signature).
     | Публичная часть RSA-ключа указывается в консоли Aitu Passport, приватная —
     | хранится только на сервере. Бэкенд подписывает ИИН алгоритмом SHA256withRSA
     | и передаёт подпись в формате base64url. Это запрещает пользователю менять
     | переданный ИИН на стороне Aitu Passport.
     |
     | Сгенерировать пару: php artisan aitu:generate-iin-keys
     | Приватный ключ задаётся либо инлайн (PEM с экранированными \n), либо путём
     | к файлу. Оставьте пустым, если защита ИИН не используется.
     */
    'iin' => [
        'private_key' => env('AITU_IIN_PRIVATE_KEY'),
        'private_key_path' => env('AITU_IIN_PRIVATE_KEY_PATH'),
    ],

    /*
     | «Валидация на стороне клиента» — URL, который Aitu Passport вызывает для
     | валидации на стороне сервиса Партнёра. Тип авторизации — Basic:
     | Aitu передаёт Authorization: Basic base64(validator_id:secret).
     |
     | validator_id — логин (выдаётся/задаётся в консоли Aitu Passport).
     | secret       — пароль (тот же, что указан в консоли).
     | Если secret пуст — проверка авторизации отключена.
     */
    'validation' => [
        'validator_id' => env('AITU_VALIDATOR_ID', ''),
        'secret' => env('AITU_CLIENT_VALIDATION_SECRET', ''),
    ],

    /*
     | KYC-верификация через Aitu Passport (KYC_PROVIDER=aitu).
     |
     | Aitu Passport сам проверяет личность пользователя и возвращает в id_token
     | только результат «пройдено / не пройдено» (например, в claim CONFIDENCE_LEVEL).
     | Для этого сервису Партнёра нужен соответствующий scope из раздела
     | «Доступы по подписке» консоли (CONFIDENCE_LEVEL и т. п.), добавленный в AITU_SCOPE.
     |
     | claims        — список ключей id_token, где может лежать статус верификации
     |                 (проверяются по порядку; берётся первый присутствующий).
     | passed_values — значения claim, которые трактуются как «верификация пройдена».
     | failed_values — значения claim, которые трактуются как «верификация не пройдена».
     | Остальные значения считаются «неизвестно» (статус KYC не меняется).
     */
    'verification' => [
        // Empty env values fall back to the defaults below (via `?:`).
        'claims' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) (env('AITU_VERIFY_CLAIMS')
                ?: 'kyc_verified,identity_verified,verified,verification_status,confidence_level,confidenceLevel')),
        ))),

        'passed_values' => array_values(array_filter(array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            explode(',', (string) (env('AITU_VERIFY_PASSED_VALUES')
                ?: 'true,1,yes,high,verified,passed,success,full,approved,green,medium')),
        ))),

        'failed_values' => array_values(array_filter(array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            explode(',', (string) (env('AITU_VERIFY_FAILED_VALUES')
                ?: 'false,0,no,low,failed,rejected,declined,red,none')),
        ))),
    ],
];
