const NAV_GROUPS = [
    {
        label: 'Обзор',
        items: [
            {
                section: 'dashboard',
                href: '/admin',
                label: 'Дашборд',
                icon: 'dashboard',
                match: (url) => url === '/admin',
            },
        ],
    },
    {
        label: 'Клиенты',
        items: [
            {
                section: 'users',
                href: '/admin/users',
                label: 'Пользователи',
                icon: 'group',
                match: (url) => url.startsWith('/admin/users'),
            },
            {
                section: 'kyc',
                href: '/admin/kyc',
                label: 'KYC',
                icon: 'verified_user',
                match: (url) => url.startsWith('/admin/kyc'),
            },
            {
                section: 'subscriptions',
                href: '/admin/subscriptions',
                label: 'Подписки',
                icon: 'card_membership',
                match: (url) => url.startsWith('/admin/subscriptions'),
            },
        ],
    },
    {
        label: 'Операции',
        items: [
            {
                section: 'orders',
                href: '/admin/orders',
                label: 'Ордера',
                icon: 'receipt_long',
                match: (url) => url.startsWith('/admin/orders'),
            },
            {
                section: 'withdrawals',
                href: '/admin/withdrawals',
                label: 'Выводы',
                icon: 'call_made',
                match: (url) => url.startsWith('/admin/withdrawals'),
            },
            {
                section: 'disputes',
                href: '/admin/disputes',
                label: 'Споры',
                icon: 'gavel',
                match: (url) => url.startsWith('/admin/disputes'),
            },
        ],
    },
    {
        label: 'Казначейство',
        items: [
            {
                section: 'finance',
                href: '/admin/finance',
                label: 'Финансы',
                icon: 'account_balance',
                match: (url) => url.startsWith('/admin/finance'),
            },
            {
                section: 'wallets',
                href: '/admin/wallets',
                label: 'Кошельки',
                icon: 'account_balance_wallet',
                match: (url) => url.startsWith('/admin/wallets'),
            },
            {
                section: 'sweeps',
                href: '/admin/sweeps',
                label: 'Sweeps',
                icon: 'sync',
                match: (url) => url.startsWith('/admin/sweeps'),
            },
        ],
    },
    {
        label: 'Система',
        items: [
            {
                section: 'settings',
                href: '/admin/settings',
                label: 'Настройки',
                icon: 'settings',
                match: (url) => url.startsWith('/admin/settings'),
            },
            {
                section: 'audit',
                href: '/admin/audit',
                label: 'Журнал',
                icon: 'history',
                match: (url) => url.startsWith('/admin/audit'),
            },
        ],
    },
];

function isEnabled(sections, section) {
    return Boolean(sections?.[section]);
}

/**
 * @returns {Array<{ label: string, items: Array<{ href: string, label: string, icon: string, match: (url: string) => boolean }> }>}
 */
export function buildAdminNavGroups(sections) {
    return NAV_GROUPS.map((group) => ({
        label: group.label,
        items: group.items.filter((item) => isEnabled(sections, item.section)),
    })).filter((group) => group.items.length > 0);
}

/** @deprecated Use buildAdminNavGroups — flat list for backwards compatibility */
export function buildAdminNavItems(sections) {
    return buildAdminNavGroups(sections).flatMap((group) => group.items);
}

export function findActiveAdminNavItem(sections, url) {
    return buildAdminNavItems(sections).find((item) => item.match(url)) ?? null;
}
