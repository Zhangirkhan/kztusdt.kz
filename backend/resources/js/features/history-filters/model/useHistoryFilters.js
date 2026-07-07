import { router } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';

export function useHistoryFilters(initialState) {
    function reload(params = {}) {
        router.get(localizedPath('/wallet/history'), {
            section: initialState.section,
            filter: initialState.filter,
            status: initialState.status,
            q: initialState.search,
            ...params,
        }, { preserveState: true, preserveScroll: true });
    }

    return {
        setSection: (section) => reload({ section, filter: 'all', status: 'all' }),
        setFilter: (filter) => reload({ filter }),
        setStatus: (status) => reload({ status }),
        setSearch: (event) => reload({ q: event.target.value }),
    };
}
