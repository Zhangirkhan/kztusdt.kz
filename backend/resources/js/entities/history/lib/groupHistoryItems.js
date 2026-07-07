import { formatHistoryGroupLabel } from '@/shared/lib/format/date';

export function groupHistoryItems(items) {
    const groups = new Map();

    for (const item of items) {
        const label = formatHistoryGroupLabel(item.created_at);
        const list = groups.get(label) ?? [];
        list.push(item);
        groups.set(label, list);
    }

    return [...groups.entries()];
}

export function countHistoryByStatus(items, status) {
    return items.filter((item) => item.status === status).length;
}
