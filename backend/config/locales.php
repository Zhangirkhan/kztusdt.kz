<?php

declare(strict_types=1);

return [
    'default' => env('APP_LOCALE', 'ru'),

    'supported' => ['ru', 'kk', 'en'],

    'cookie' => 'app_locale',

    'labels' => [
        'ru' => 'Русский',
        'kk' => 'Қазақша',
        'en' => 'English',
    ],
];
