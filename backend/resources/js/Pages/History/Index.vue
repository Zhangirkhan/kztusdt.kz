<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import HistoryPanel from '@/widgets/history-panel/ui/HistoryPanel.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    section: { type: String, default: 'wallet' },
    filter: { type: String, default: 'all' },
    status: { type: String, default: 'all' },
    search: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    stats: Object,
    asset: String,
});
</script>

<template>
    <Head title="История" />

    <ExchangeLayout>
        <template #title>История</template>
        <HistoryPanel
            :section="section"
            :filter="filter"
            :status="status"
            :search="search"
            :items="items"
        />
    </ExchangeLayout>
</template>
