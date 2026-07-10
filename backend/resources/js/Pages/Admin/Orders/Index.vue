<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatKzt, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    orders: Object,
    filterStatus: String,
    filterDirection: String,
    stats: Object,
});

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ждёт оплату KZT',
    payment_proof_uploaded: 'Скрин загружен',
    pending_admin_confirmation: 'Ждёт подтверждения',
    kzt_sent: 'KZT отправлены',
    kzt_received: 'KZT получены',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    dispute: 'Спор',
    manual_review: 'Ручная проверка',
};

const statItems = computed(() => [
    { label: 'Ожидают действия', value: props.stats.pending, color: '#faad14' },
    { label: 'Выполнено', value: props.stats.completed, color: '#52c41a' },
    { label: 'Отменено / ошибки', value: props.stats.cancelled, color: '#ff4d4f' },
]);

const statusOptions = [
    { label: 'Активные', value: 'active' },
    { label: 'Ждут подтверждения', value: 'pending_admin_confirmation' },
    { label: 'Выполненные', value: 'completed' },
    { label: 'Отменённые', value: 'cancelled' },
    { label: 'Все', value: 'all' },
];

const directionOptions = [
    { label: 'Покупка + продажа', value: 'all' },
    { label: 'Покупка', value: 'buy' },
    { label: 'Продажа', value: 'sell' },
];

const columns = [
    { title: 'Заявка', key: 'order' },
    { title: 'Сумма', key: 'amount', width: 160 },
    { title: 'Статус', key: 'status', width: 180 },
    { title: 'Время', key: 'time', width: 160 },
    { title: '', key: 'actions', width: 90, align: 'right' },
];

function setFilter(status) {
    router.get('/admin/orders', { status, direction: props.filterDirection }, { preserveState: true });
}

function setDirection(direction) {
    router.get('/admin/orders', { status: props.filterStatus, direction }, { preserveState: true });
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU');
}
</script>

<template>
    <Head title="Заявки обмена" />

    <AdminLayout>
        <template #title>Заявки обмена KZT/USDT</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="filterStatus" :options="statusOptions" @change="setFilter" />
            <AdminFilters :model-value="filterDirection" :options="directionOptions" size="small" @change="setDirection" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="orders.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'order'">
                            <div>
                                <a-typography-text strong>
                                    №{{ record.id }} · {{ record.direction === 'buy' ? 'Покупка' : 'Продажа' }}
                                </a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.user?.name ?? '—' }} · {{ record.user?.phone ?? '—' }}
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            <a-typography-text strong>{{ formatKzt(record.fiat_amount) }} ₸</a-typography-text>
                            <div class="admin-ant-meta">{{ formatUsdt(record.crypto_amount, 2) }} USDT</div>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">
                                {{ statusLabels[record.status] ?? record.status }}
                            </a-tag>
                            <div v-if="record.fiat_payment_request?.proof_file_path" class="admin-ant-meta">📎 скрин</div>
                        </template>

                        <template v-else-if="column.key === 'time'">
                            <span class="admin-ant-meta">{{ formatDate(record.created_at) }}</span>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="`/admin/orders/${record.id}`">
                                <a-button type="link" size="small">Открыть</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty description="Нет заявок" />
                    </template>
                </a-table>

                <AdminPagination :pagination="orders" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
