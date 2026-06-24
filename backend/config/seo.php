<?php

declare(strict_types=1);

$siteUrl = rtrim((string) env('APP_URL', 'https://kztusdt.kz'), '/');

return [
    'site_url' => $siteUrl,

    'image' => env('SEO_IMAGE', $siteUrl.'/icons/icon-192.png'),

    'indexable_routes' => [
        'auth.phone',
        'legal.index',
        'legal.show',
    ],
];
