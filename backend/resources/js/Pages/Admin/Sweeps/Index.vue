<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    sweeps: Object,
    filterStatus: String,
    stats: Object,
    enabled: Boolean,
});

const statItems = computed(() => [
    { label: 'Ждут газ', value: props.stats.waiting_gas, color: '#faad14' },
    { label: 'В процессе', value: props.stats.in_progress, color: '#1677ff' },
    { label: 'Собрано', value: props.stats.swept, color: '#52c41a' },
    { label: 'Требуют внимания', value: props.stats.attention, color: '#ff4d4f' },
]);

const filterOptions = [
    { label: 'Активные', value: 'active' },
    { label: 'Собранные', value: 'swept' },
    { label: 'Внимание', value: 'attention' },
    { label: 'Все', value: 'all' },
];

const columns = [
    { title: 'Sweep', key: 'sweep' },
    { title: 'Сумма', key: 'amount', width: 140 },
    { title: 'Статус', key: 'status', width: 140 },
    { title: '', key: 'actions', width: 110, align: 'right' },
];

function setFilter(status) {
    router.get('/admin/sweeps', { status }, { preserveState: true });
}

function retry(id) {
    router.post(`/admin/sweeps/${id}/retry`, {}, { preserveScroll: true });
}

function short(hash) {
    return hash ? `${hash.slice(0, 8)}…${hash.slice(-6)}` : '—';
}
</script>

<template>
    <Head title="Sweeps" />

    <AdminLayout>
        <template #title>Sweep депозитов</template>

        <AdminPage>
            <a-alert
                v-if="!enabled"
                type="warning"
                show-icon
                message="Sweeper выключен (SWEEP_ENABLED=false). Транзакции не отправляются."
                class="admin-ant-block"
            />

            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="filterStatus" :options="filterOptions" @change="setFilter" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="sweeps.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'sweep'">
                            <div>
                                <a-typography-text strong>#{{ record.id }} · {{ record.asset }}</a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.user?.phone ?? '—' }} · {{ short(record.from_address) }} → {{ short(record.to_address) }}
                                </div>
                                <a-typography-text v-if="record.last_error" type="danger" class="admin-ant-meta">
                                    {{ record.last_error }}
                                </a-typography-text>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            <a-typography-text strong>{{ formatUsdt(record.amount, 8) }}</a-typography-text>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <a-button
                                v-if="['manual_review', 'failed'].includes(record.status)"
                                type="primary"
                                size="small"
                                @click="retry(record.id)"
                            >
                                Повторить
                            </a-button>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty description="Нет записей" />
                    </template>
                </a-table>

                <AdminPagination :pagination="sweeps" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
