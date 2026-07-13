import { i18n } from '@/i18n';
import { isActivePath, localizedPathFor, unlocalizedPath } from '@/utils/localizedPath';

export function buildExchangeNavItems(canUseWallet, locale = 'ru') {
    const t = i18n.global.t;
    const kycHref = localizedPathFor(locale, '/kyc');

    return [
        {
            href: canUseWallet ? localizedPathFor(locale, '/wallet') : kycHref,
            label: t('nav.wallet'),
            icon: 'wallet',
            locked: !canUseWallet,
            active: (url) => {
                const path = unlocalizedPath(url);

                return path === '/wallet' || path.startsWith('/wallet?');
            },
        },
        {
            href: canUseWallet ? localizedPathFor(locale, '/exchange') : kycHref,
            label: t('nav.exchange'),
            icon: 'exchange',
            locked: !canUseWallet,
            active: (url) => isActivePath(url, '/exchange') || isActivePath(url, '/market'),
        },
        {
            href: localizedPathFor(locale, '/profile'),
            label: t('nav.profile'),
            icon: 'user',
            locked: false,
            active: (url) => isActivePath(url, '/profile'),
        },
    ];
}
