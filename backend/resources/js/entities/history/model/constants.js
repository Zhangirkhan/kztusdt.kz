import { i18n } from '@/i18n';

export function historyStatusFilters() {
    return [
        { id: 'all', label: i18n.global.t('history.filters.all') },
        { id: 'COMPLETED', label: i18n.global.t('history.filters.completed') },
        { id: 'PENDING', label: i18n.global.t('history.filters.pending') },
        { id: 'FAILED', label: i18n.global.t('history.filters.failed') },
    ];
}

function walletSubTabs() {
    return [
        { id: 'all', label: i18n.global.t('history.subTabs.all'), icon: 'sync_alt', tone: 'all' },
        { id: 'deposit', label: i18n.global.t('history.subTabs.deposit'), icon: 'south', tone: 'in' },
        { id: 'withdraw', label: i18n.global.t('history.subTabs.withdraw'), icon: 'north', tone: 'out' },
    ];
}

function exchangeSubTabs() {
    return [
        { id: 'all', label: i18n.global.t('history.subTabs.all'), icon: 'sync_alt', tone: 'all' },
        { id: 'buy', label: i18n.global.t('history.subTabs.buy'), icon: 'south', tone: 'in' },
        { id: 'sell', label: i18n.global.t('history.subTabs.sell'), icon: 'north', tone: 'out' },
    ];
}

export function historySubTabs(section) {
    return section === 'exchange' ? exchangeSubTabs() : walletSubTabs();
}
