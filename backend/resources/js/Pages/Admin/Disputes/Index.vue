<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    disputes: Array,
    stats: Object,
});

const statItems = computed(() => [
    { label: 'Открытые', value: props.stats.open, color: '#faad14' },
]);

const columns = [
    { title: 'Ордер', dataIndex: 'id', key: 'id', width: 90 },
    { title: 'Клиент', dataIndex: 'user', key: 'user' },
    { title: 'Направление', key: 'direction', width: 120 },
    { title: 'Сумма', key: 'amount' },
    { title: 'Статус', key: 'status', width: 140 },
    { title: 'Дата', key: 'date', width: 170 },
    { title: '', key: 'actions', width: 90, align: 'right' },
];
</script>

<template>
    <Head title="Споры" />

    <AdminLayout>
        <template #title>Споры и ручная проверка</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="disputes"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'id'">#{{ record.id }}</template>

                        <template v-else-if="column.key === 'direction'">
                            {{ record.direction === 'buy' ? 'Покупка' : 'Продажа' }}
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            {{ record.fiat_amount }} ₸ / {{ record.crypto_amount }} USDT
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
                        <a-empty description="Нет споров" />
                    </template>
                </a-table>
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
