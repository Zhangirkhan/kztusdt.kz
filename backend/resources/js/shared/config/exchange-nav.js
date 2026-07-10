import { isActivePath, localizedPath, unlocalizedPath } from '@/utils/localizedPath';

export function buildExchangeNavItems(canUseWallet) {
    const kycHref = localizedPath('/kyc');

    return [
        {
            href: canUseWallet ? localizedPath('/wallet') : kycHref,
            label: 'Кошелёк',
            icon: 'wallet',
            locked: !canUseWallet,
            active: (url) => {
                const path = unlocalizedPath(url);

                return path === '/wallet' || path.startsWith('/wallet?');
            },
        },
        {
            href: canUseWallet ? localizedPath('/exchange') : kycHref,
            label: 'Обмен',
            icon: 'exchange',
            locked: !canUseWallet,
            active: (url) => isActivePath(url, '/exchange') || isActivePath(url, '/market'),
        },
        {
            href: localizedPath('/profile'),
            label: 'Профиль',
            icon: 'user',
            locked: false,
            active: (url) => isActivePath(url, '/profile'),
        },
    ];
}
