import { router } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';

export function useHistoryFilters(getState) {
    function currentState() {
        return typeof getState === 'function' ? getState() : getState;
    }

    function reload(params = {}) {
        const state = currentState();

        router.get(localizedPath('/wallet/history'), {
            section: state.section,
            filter: state.filter,
            status: state.status,
            q: state.search,
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
