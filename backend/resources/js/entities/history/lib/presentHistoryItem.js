import { i18n } from '@/i18n';

export function historyStatusLabel(status) {
    if (status === 'COMPLETED') {
        return i18n.global.t('history.filters.completed');
    }

    if (status === 'FAILED') {
        return i18n.global.t('history.filters.failed');
    }

    return i18n.global.t('history.filters.pending');
}

export function historyIconTone(item) {
    if (item.kind === 'buy' || item.kind === 'deposit') {
        return 'in';
    }

    if (item.kind === 'sell' || item.kind === 'withdraw') {
        return 'out';
    }

    return 'swap';
}

export function historyIconName(item) {
    if (item.kind === 'buy' || item.kind === 'deposit') {
        return 'south';
    }

    if (item.kind === 'sell' || item.kind === 'withdraw') {
        return 'north';
    }

    return 'currency_exchange';
}

export function historyStatusBadgeClass(status) {
    return {
        'status-badge--success': status === 'COMPLETED',
        'status-badge--pending': status === 'PENDING',
        'status-badge--error': status === 'FAILED',
    };
}
