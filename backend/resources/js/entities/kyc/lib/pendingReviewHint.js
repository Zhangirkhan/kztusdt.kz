import { i18n } from '@/i18n';

export function pendingReviewHint({ profileProvider, manualEnabled, provider }) {
    if (profileProvider === 'manual' || manualEnabled) {
        return i18n.global.t('kyc.pendingHint.manual');
    }

    if (provider === 'sumsub') {
        return i18n.global.t('kyc.pendingHint.sumsub');
    }

    return i18n.global.t('kyc.pendingHint.default');
}

export const KYC_DOCUMENT_TYPES = [
    { value: 'id_card', label: i18n.global.t('kyc.documentTypes.idCard') },
    { value: 'passport', label: i18n.global.t('kyc.documentTypes.passport') },
];
