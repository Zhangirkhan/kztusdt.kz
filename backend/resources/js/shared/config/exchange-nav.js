import { isActivePath, localizedPath, unlocalizedPath } from '@/utils/localizedPath';

export function buildExchangeNavItems(canUseWallet) {
    const kycHref = localizedPath('/kyc');

    return [
        {
            href: canUseWallet ? localizedPath('/wallet') : kycHref,
            label: 'Кошелёк',
            icon: 'account_balance_wallet',
            locked: !canUseWallet,
            active: (url) => {
                const path = unlocalizedPath(url);

                return (path === '/wallet' || path.startsWith('/wallet?'))
                    && !path.startsWith('/wallet/history');
            },
        },
        {
            href: canUseWallet ? localizedPath('/wallet/history') : kycHref,
            label: 'История',
            icon: 'history',
            locked: !canUseWallet,
            active: (url) => isActivePath(url, '/wallet/history'),
        },
        {
            href: canUseWallet ? localizedPath('/exchange') : kycHref,
            label: 'Обмен',
            icon: 'currency_exchange',
            locked: !canUseWallet,
            active: (url) => isActivePath(url, '/exchange') || isActivePath(url, '/market'),
        },
        {
            href: localizedPath('/profile'),
            label: 'Профиль',
            icon: 'person',
            locked: false,
            active: (url) => isActivePath(url, '/profile'),
        },
    ];
}
