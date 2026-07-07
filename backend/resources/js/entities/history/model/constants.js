export const HISTORY_STATUS_FILTERS = [
    { id: 'all', label: 'Все' },
    { id: 'COMPLETED', label: 'Завершено' },
    { id: 'PENDING', label: 'В обработке' },
    { id: 'FAILED', label: 'Ошибка' },
];

export const HISTORY_WALLET_SUB_TABS = [
    { id: 'all', label: 'Все', icon: 'sync_alt' },
    { id: 'deposit', label: 'Ввод', icon: 'south' },
    { id: 'withdraw', label: 'Вывод', icon: 'north' },
];

export const HISTORY_EXCHANGE_SUB_TABS = [
    { id: 'all', label: 'Все', icon: 'sync_alt' },
    { id: 'buy', label: 'Покупка', icon: 'south' },
    { id: 'sell', label: 'Продажа', icon: 'north' },
];

export function historySubTabs(section) {
    return section === 'exchange' ? HISTORY_EXCHANGE_SUB_TABS : HISTORY_WALLET_SUB_TABS;
}
