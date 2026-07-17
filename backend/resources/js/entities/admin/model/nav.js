const NAV_GROUPS = [
    {
        labelKey: 'admin.nav.groups.overview',
        items: [
            {
                section: 'dashboard',
                href: '/admin',
                labelKey: 'admin.nav.items.dashboard',
                icon: 'dashboard',
                match: (url) => url === '/admin',
            },
        ],
    },
    {
        labelKey: 'admin.nav.groups.clients',
        items: [
            {
                section: 'users',
                href: '/admin/users',
                labelKey: 'admin.nav.items.users',
                icon: 'group',
                match: (url) => url.startsWith('/admin/users'),
            },
            {
                section: 'kyc',
                href: '/admin/kyc',
                labelKey: 'admin.nav.items.kyc',
                icon: 'verified_user',
                match: (url) => url.startsWith('/admin/kyc'),
            },
            {
                section: 'subscriptions',
                href: '/admin/subscriptions',
                labelKey: 'admin.nav.items.subscriptions',
                icon: 'card_membership',
                match: (url) => url.startsWith('/admin/subscriptions'),
            },
        ],
    },
    {
        labelKey: 'admin.nav.groups.operations',
        items: [
            {
                section: 'support',
                href: '/admin/support',
                labelKey: 'admin.nav.items.support',
                icon: 'chat',
                match: (url) => url.startsWith('/admin/support'),
            },
            {
                section: 'listings',
                href: '/admin/listings',
                labelKey: 'admin.nav.items.listings',
                icon: 'campaign',
                match: (url) => url.startsWith('/admin/listings'),
            },
            {
                section: 'orders',
                href: '/admin/orders',
                labelKey: 'admin.nav.items.orders',
                icon: 'receipt_long',
                match: (url) => url.startsWith('/admin/orders'),
            },
            {
                section: 'withdrawals',
                href: '/admin/withdrawals',
                labelKey: 'admin.nav.items.withdrawals',
                icon: 'call_made',
                match: (url) => url.startsWith('/admin/withdrawals'),
            },
            {
                section: 'appeals',
                href: '/admin/appeals',
                labelKey: 'admin.nav.items.appeals',
                icon: 'gavel',
                match: (url) => url.startsWith('/admin/appeals'),
            },
        ],
    },
    {
        labelKey: 'admin.nav.groups.treasury',
        items: [
            {
                section: 'finance',
                href: '/admin/finance',
                labelKey: 'admin.nav.items.finance',
                icon: 'account_balance',
                match: (url) => url.startsWith('/admin/finance'),
            },
            {
                section: 'wallets',
                href: '/admin/wallets',
                labelKey: 'admin.nav.items.wallets',
                icon: 'account_balance_wallet',
                match: (url) => url.startsWith('/admin/wallets'),
            },
            {
                section: 'sweeps',
                href: '/admin/sweeps',
                labelKey: 'admin.nav.items.sweeps',
                icon: 'sync',
                match: (url) => url.startsWith('/admin/sweeps'),
            },
        ],
    },
    {
        labelKey: 'admin.nav.groups.system',
        items: [
            {
                section: 'settings',
                href: '/admin/settings',
                labelKey: 'admin.nav.items.settings',
                icon: 'settings',
                match: (url) => url.startsWith('/admin/settings'),
            },
            {
                section: 'audit',
                href: '/admin/audit',
                labelKey: 'admin.nav.items.audit',
                icon: 'history',
                match: (url) => url.startsWith('/admin/audit'),
            },
        ],
    },
];

function isEnabled(sections, section) {
    return Boolean(sections?.[section]);
}

export function buildAdminNavGroups(sections, t) {
    return NAV_GROUPS.map((group) => ({
        label: t(group.labelKey),
        items: group.items
            .filter((item) => isEnabled(sections, item.section))
            .map((item) => ({
                ...item,
                label: t(item.labelKey),
            })),
    })).filter((group) => group.items.length > 0);
}

/** @deprecated Use buildAdminNavGroups — flat list for backwards compatibility */
export function buildAdminNavItems(sections, t) {
    return buildAdminNavGroups(sections, t).flatMap((group) => group.items);
}

export function findActiveAdminNavItem(sections, url, t) {
    return buildAdminNavItems(sections, t).find((item) => item.match(url)) ?? null;
}
