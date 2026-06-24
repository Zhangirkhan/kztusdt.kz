<?php

declare(strict_types=1);

return [
    'groups' => [
        'guest' => [
            'legal.*',
            'auth.phone',
            'auth.phone.store',
            'auth.telegram.wait',
            'locale.update',
            'login',
            'logout',
            'password.*',
            'register',
            'verification.*',
        ],
        'app' => [
            'legal.*',
            'auth.phone',
            'auth.phone.store',
            'auth.telegram.wait',
            'locale.update',
            'home',
            'wallet',
            'exchange',
            'exchange.orders.*',
            'withdraw',
            'withdraw.*',
            'kyc',
            'kyc.*',
            'profile.*',
            'logout',
        ],
        'admin' => [
            'admin.*',
            'home',
            'logout',
            'locale.update',
            'login',
        ],
        'admin_security' => [
            'admin.account',
            'admin.dashboard',
            'admin.kyc.*',
            'admin.orders.*',
            'admin.withdrawals.*',
            'logout',
            'locale.update',
            'login',
        ],
        'admin_exchange' => [
            'admin.account',
            'admin.orders.*',
            'home',
            'logout',
            'locale.update',
            'login',
        ],
    ],
];
