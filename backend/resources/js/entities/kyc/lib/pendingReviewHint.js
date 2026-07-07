export function pendingReviewHint({ profileProvider, manualEnabled, provider }) {
    if (profileProvider === 'manual' || manualEnabled) {
        return 'Документы на проверке службой безопасности. Обычно решение принимается в течение рабочего дня.';
    }

    if (provider === 'sumsub') {
        return 'Документы на проверке. Обычно Sumsub отвечает за 1–2 минуты.';
    }

    return 'Верификация в обработке.';
}

export const KYC_DOCUMENT_TYPES = [
    { value: 'id_card', label: 'Удостоверение' },
    { value: 'passport', label: 'Паспорт' },
];
