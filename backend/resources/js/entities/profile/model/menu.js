import { localizedPath } from '@/utils/localizedPath';

export function buildProfileMenuItems(languageLabel, canUseWallet = true) {
    return [
        { href: localizedPath('/profile/personal'), icon: 'person', label: 'Личные данные' },
        {
            href: canUseWallet ? localizedPath('/profile/bank') : localizedPath('/kyc'),
            icon: 'account_balance',
            label: 'Банковские реквизиты',
            locked: !canUseWallet,
        },
        { href: localizedPath('/profile/security'), icon: 'shield', label: 'Безопасность' },
        { href: localizedPath('/profile/language'), icon: 'language', label: 'Сменить язык', value: languageLabel },
        { href: localizedPath('/profile/notifications'), icon: 'notifications', label: 'Уведомления' },
        { href: localizedPath('/profile/support'), icon: 'headset_mic', label: 'Служба поддержки' },
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
