<?php

declare(strict_types=1);

return [
    'fee_default' => (float) env('FEE_PERCENT_DEFAULT', 0.5),
    'fee_subscription' => (float) env('FEE_PERCENT_SUBSCRIPTION', 0.05),

    'tariffs' => [
        'standard' => [
            'code' => 'standard',
            'name' => 'Сейчас',
            'fee_percent' => (float) env('FEE_PERCENT_DEFAULT', 0.5),
            'timing' => 'Мгновенно',
            'description' => 'Обмен и вывод USDT в приоритетном порядке.',
        ],
        'subscription' => [
            'code' => 'subscription',
            'name' => 'Через день',
            'fee_percent' => (float) env('FEE_PERCENT_SUBSCRIPTION', 0.05),
            'timing' => 'До 24 часов',
            'description' => 'Если валюта нужна не срочно — минимальная комиссия.',
        ],
    ],
    'default_fiat' => 'KZT',
    'default_crypto' => 'USDT',
    'default_network' => 'BEP20',

    // Order limits (human units).
    'min_buy_kzt' => (float) env('EXCHANGE_MIN_BUY_KZT', 5000),
    'max_buy_kzt' => (float) env('EXCHANGE_MAX_BUY_KZT', 5000000),
    'min_sell_usdt' => (float) env('EXCHANGE_MIN_SELL_USDT', 5),
    'max_sell_usdt' => (float) env('EXCHANGE_MAX_SELL_USDT', 10000),

    // Minutes for countdown timers on the client order page.
    'confirmation_term_minutes' => (int) env('EXCHANGE_CONFIRMATION_TERM_MINUTES', 20),

    'payment_term_minutes' => [
        '15_min' => 15,
        '30_min' => 30,
        '60_min' => 60,
        '180_min' => 180,
    ],

    // Bank requisites of the exchanger shown to clients for KZT transfers (buy flow).
    'requisites' => [
        'bank_name' => env('EXCHANGE_BANK_NAME', env('COMPANY_BANK_NAME', 'АО «Банк ЦентрКредит»')),
        'recipient_name' => env('EXCHANGE_BANK_RECIPIENT', env('COMPANY_LEGAL_NAME', 'ТОО «100k.kz»')),
        'recipient_account' => env('EXCHANGE_BANK_ACCOUNT', env('COMPANY_BANK_ACCOUNT', 'KZ428562203154554848')),
        'bin' => env('EXCHANGE_BANK_BIN', env('COMPANY_BIN', '260340021560')),
        'kbe' => env('EXCHANGE_BANK_KBE', env('COMPANY_KBE', '17')),
        'bic' => env('EXCHANGE_BANK_BIC', env('COMPANY_BANK_BIC', 'KCJBKZKX')),
        'comment' => env('EXCHANGE_BANK_COMMENT', 'Укажите номер заявки в комментарии к переводу'),
    ],

    // Live USDT/KZT rate settings.
    'rate' => [
        'symbol' => env('RATE_SYMBOL', 'USDTKZT'),
        'cache_ttl' => (int) env('RATE_CACHE_TTL', 120), // seconds
        'markup_buy' => (float) env('RATE_MARKUP_BUY', 1.0), // % added when client buys USDT
        'markup_sell' => (float) env('RATE_MARKUP_SELL', 1.0), // % subtracted when client sells USDT
        'fallback' => (float) env('RATE_FALLBACK', 510.50), // used when API is down and no cached rate exists
    ],
];
