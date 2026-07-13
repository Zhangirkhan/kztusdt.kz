import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const ORDER_STATUS_KEYS = [
    'created',
    'awaiting_kzt_payment',
    'payment_proof_uploaded',
    'pending_admin_confirmation',
    'kzt_sent',
    'kzt_received',
    'crypto_sending',
    'crypto_sent',
    'completed',
    'cancelled',
    'failed',
    'dispute',
    'manual_review',
];

export function useOrderStatusLabels(namespace = 'order.status') {
    const { t } = useI18n();

    return computed(() => Object.fromEntries(
        ORDER_STATUS_KEYS.map((key) => [key, t(`${namespace}.${key}`)]),
    ));
}

export function useWithdrawalStatusLabels(namespace = 'withdraw.status') {
    const { t } = useI18n();
    const keys = [
        'created',
        'awaiting_telegram_confirmation',
        'pending_review',
        'approved',
        'sending',
        'sent',
        'completed',
        'cancelled',
        'failed',
        'rejected',
        'needs_reconcile',
    ];

    return computed(() => Object.fromEntries(
        keys.map((key) => [key, t(`${namespace}.${key}`)]),
    ));
}

export function useDepositStatusLabels(namespace = 'wallet.depositStatus') {
    const { t } = useI18n();
    const keys = ['detected', 'confirmed', 'credited', 'failed'];

    return computed(() => Object.fromEntries(
        keys.map((key) => [key, t(`${namespace}.${key}`)]),
    ));
}
