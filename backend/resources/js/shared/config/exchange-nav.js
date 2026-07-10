import { i18n } from '@/i18n';
import { isActivePath, localizedPath, unlocalizedPath } from '@/utils/localizedPath';

export function buildExchangeNavItems(canUseWallet) {
    const t = i18n.global.t;
    const kycHref = localizedPath('/kyc');

    return [
        {
            href: canUseWallet ? localizedPath('/wallet') : kycHref,
            label: t('nav.wallet'),
            icon: 'wallet',
            locked: !canUseWallet,
            active: (url) => {
                const path = unlocalizedPath(url);

                return path === '/wallet' || path.startsWith('/wallet?');
            },
        },
        {
            href: canUseWallet ? localizedPath('/exchange') : kycHref,
            label: t('nav.exchange'),
            icon: 'exchange',
            locked: !canUseWallet,
            active: (url) => isActivePath(url, '/exchange') || isActivePath(url, '/market'),
        },
        {
            href: localizedPath('/profile'),
            label: t('nav.profile'),
            icon: 'user',
            locked: false,
            active: (url) => isActivePath(url, '/profile'),
        },
    ];
}
