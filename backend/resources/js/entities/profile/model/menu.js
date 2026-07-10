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
        { href: localizedPath('/profile/notifications'), icon: 'notifications', labelKey: 'profile.menu.notifications' },
        { href: localizedPath('/profile/support'), icon: 'support_agent', labelKey: 'profile.menu.support' },
    ];
}

export function isProfileKycVerified(profile) {
    return profile?.kyc_status === 'approved';
}

export function kycGateMessage(kycStatus) {
    if (kycStatus === 'pending_review') {
        return 'Заявка на KYC на проверке. Кошелёк, обмен и вывод откроются после одобрения.';
    }

    if (kycStatus === 'rejected') {
        return 'KYC отклонён. Пройдите верификацию заново, чтобы открыть кошелёк и обмен.';
    }

    return 'Пройдите KYC-верификацию, чтобы открыть кошелёк, обмен USDT и вывод средств.';
}
