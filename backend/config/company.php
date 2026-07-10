<?php

declare(strict_types=1);

return [
    'name' => env('COMPANY_NAME', env('APP_NAME', 'kztusdt.kz')),

    'legal_name' => env('COMPANY_LEGAL_NAME', 'ТОО «100k.kz»'),

    'director' => env('COMPANY_DIRECTOR', 'Нургалиев Жангирхан Сапарулы'),

    'bin' => env('COMPANY_BIN', '260340021560'),

    'address' => env(
        'COMPANY_ADDRESS',
        '050000, Республика Казахстан, г. Алматы, Бостандыкский район, проспект Аль-Фараби, д. 19, корп. 1Б, к. 207',
    ),

    'bank_name' => env('COMPANY_BANK_NAME', 'АО «Банк ЦентрКредит»'),

    'bank_account' => env('COMPANY_BANK_ACCOUNT', 'KZ428562203154554848'),

    'bank_bic' => env('COMPANY_BANK_BIC', 'KCJBKZKX'),

    'kbe' => env('COMPANY_KBE', '17'),

    'tagline' => env('COMPANY_TAGLINE', 'Крипто-обменник USDT / KZT'),

    'description' => env(
        'COMPANY_DESCRIPTION',
        'Официальный сервис обмена USDT на казахстанский тенге. '
        .'Регистрация по номеру телефона, верификация личности и персональный кошелёк для безопасных операций.',
    ),

    'home_intro' => env(
        'COMPANY_HOME_INTRO',
        'Онлайн-сервис для обмена USDT на тенге и обратно. '
        .'Работаем в Казахстане: актуальный курс, прозрачная комиссия, верификация клиентов и поддержка.',
    ),

    'features' => [
        'Обмен USDT ↔ KZT по актуальному курсу',
        'Вход и подтверждение через WhatsApp',
        'KYC-верификация и выделенный кошелёк',
        'Прозрачная комиссия и история операций',
    ],

    'support_email' => env('COMPANY_SUPPORT_EMAIL', 'support@kztusdt.kz'),

    'website' => env('COMPANY_WEBSITE', env('APP_URL')),

    'documents_updated_at' => env('COMPANY_DOCUMENTS_UPDATED_AT', '2026-06-10'),
];
