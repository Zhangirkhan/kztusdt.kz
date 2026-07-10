import { i18n } from '@/i18n';
import { localizedPath } from '@/utils/localizedPath';

export function buildProfileMenuItems(languageLabel, canUseWallet = true) {
    return [
        { href: localizedPath('/profile/personal'), icon: 'badge', labelKey: 'profile.menu.personal' },
        {
            href: canUseWallet ? localizedPath('/profile/bank') : localizedPath('/kyc'),
            icon: 'account_balance_wallet',
            labelKey: 'profile.menu.bank',
            locked: !canUseWallet,
        },
        { href: localizedPath('/profile/security'), icon: 'security', labelKey: 'profile.menu.security' },
        { href: localizedPath('/profile/language'), icon: 'translate', labelKey: 'profile.menu.language', value: languageLabel },
        { href: localizedPath('/profile/appearance'), icon: 'dark_mode', labelKey: 'profile.menu.appearance' },
        { href: localizedPath('/profile/notifications'), icon: 'notifications', labelKey: 'profile.menu.notifications' },
        { href: localizedPath('/profile/support'), icon: 'support_agent', labelKey: 'profile.menu.support' },
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
