<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    tab: String,
    status: String,
    items: Array,
    stats: Object,
});

const statItems = computed(() => [
    { label: 'Выводы на проверке', value: props.stats.pending_withdrawals, color: '#faad14' },
    { label: 'Депозиты в обработке', value: props.stats.pending_deposits, color: '#1677ff' },
    { label: 'Завершённые выводы', value: props.stats.completed_withdrawals, color: '#52c41a' },
    { label: 'Зачисленные депозиты', value: props.stats.credited_deposits, color: '#52c41a' },
]);

const tabOptions = [
    { label: 'Выводы', value: 'withdrawals' },
    { label: 'Депозиты', value: 'deposits' },
];

const statusOptions = [
    { label: 'Активные', value: 'active' },
    { label: 'Завершённые', value: 'completed' },
    { label: 'Ошибки', value: 'failed' },
    { label: 'Все', value: 'all' },
];

const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: 'Тип', key: 'type', width: 100 },
    { title: 'Клиент', dataIndex: 'user', key: 'user' },
    { title: 'Сумма', dataIndex: 'amount', key: 'amount' },
    { title: 'Статус', key: 'status', width: 140 },
    { title: 'Дата', key: 'date', width: 170 },
    { title: '', key: 'actions', width: 90, align: 'right' },
];

function reload(params = {}) {
    router.get('/admin/finance', params, { preserveState: true });
}
</script>

<template>
    <Head title="Финансы" />

    <AdminLayout>
        <template #title>Финансы</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="tab" :options="tabOptions" @change="(v) => reload({ tab: v, status })" />
            <AdminFilters :model-value="status" :options="statusOptions" size="small" @change="(v) => reload({ tab, status: v })" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="items"
                    :pagination="false"
                    :row-key="(record) => `${record.type}-${record.id}`"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'id'">#{{ record.id }}</template>

                        <template v-else-if="column.key === 'type'">
                            {{ record.type === 'deposit' ? 'Депозит' : 'Вывод' }}
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'date'">
                            {{ record.created_at ? new Date(record.created_at).toLocaleString('ru-RU') : '—' }}
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="record.href">
                                <a-button type="link" size="small">Открыть</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty description="Нет операций" />
                    </template>
                </a-table>
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
