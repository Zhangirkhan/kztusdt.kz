import { i18n } from '@/i18n';
import { localizedPathFor } from '@/utils/localizedPath';

export function buildProfileMenuItems(canUseWallet = true, locale = 'ru') {
    return [
        { href: localizedPathFor(locale, '/profile/personal'), icon: 'badge', labelKey: 'profile.menu.personal' },
        {
            href: canUseWallet ? localizedPathFor(locale, '/profile/bank') : localizedPathFor(locale, '/kyc'),
            icon: 'account_balance_wallet',
            labelKey: 'profile.menu.bank',
            locked: !canUseWallet,
        },
        { href: localizedPathFor(locale, '/profile/security'), icon: 'security', labelKey: 'profile.menu.security' },
        { href: localizedPathFor(locale, '/profile/appearance'), icon: 'dark_mode', labelKey: 'profile.menu.appearance' },
        { href: localizedPathFor(locale, '/profile/notifications'), icon: 'notifications', labelKey: 'profile.menu.notifications' },
        { href: localizedPathFor(locale, '/profile/referrals'), icon: 'group_add', labelKey: 'profile.menu.referrals' },
        { href: localizedPathFor(locale, '/profile/support'), icon: 'support_agent', labelKey: 'profile.menu.support' },
    ];
}

export function isProfileKycVerified(profile) {
    return profile?.kyc_status === 'approved';
}

export function kycGateMessage(kycStatus) {
    const t = i18n.global.t;

    if (kycStatus === 'pending_review') {
        return t('profile.kycGate.pending_review');
    }

    if (kycStatus === 'rejected') {
        return t('profile.kycGate.rejected');
    }

    return t('profile.kycGate.default');
}
