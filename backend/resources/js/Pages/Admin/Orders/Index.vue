<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatKzt, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    orders: Object,
    filterStatus: String,
    filterDirection: String,
    stats: Object,
});

const { t } = useI18n();

const statusLabels = computed(() => ({
    created: t('admin.orders.status.created'),
    awaiting_kzt_payment: t('admin.orders.status.awaiting_kzt_payment'),
    payment_proof_uploaded: t('admin.orders.status.payment_proof_uploaded'),
    pending_admin_confirmation: t('admin.orders.status.pending_admin_confirmation'),
    kzt_sent: t('admin.orders.status.kzt_sent'),
    kzt_received: t('admin.orders.status.kzt_received'),
    completed: t('admin.orders.status.completed'),
    cancelled: t('admin.orders.status.cancelled'),
    failed: t('admin.orders.status.failed'),
    dispute: t('admin.orders.status.dispute'),
    manual_review: t('admin.orders.status.manual_review'),
}));

const statItems = computed(() => [
    { label: t('admin.orders.index.stats.pending'), value: props.stats.pending, color: '#faad14' },
    { label: t('admin.orders.index.stats.completed'), value: props.stats.completed, color: '#52c41a' },
    { label: t('admin.orders.index.stats.cancelled'), value: props.stats.cancelled, color: '#ff4d4f' },
]);

const statusOptions = computed(() => [
    { label: t('admin.orders.index.filters.active'), value: 'active' },
    { label: t('admin.orders.index.filters.pendingConfirmation'), value: 'pending_admin_confirmation' },
    { label: t('admin.orders.index.filters.completed'), value: 'completed' },
    { label: t('admin.orders.index.filters.cancelled'), value: 'cancelled' },
    { label: t('admin.orders.index.filters.all'), value: 'all' },
]);

const directionOptions = computed(() => [
    { label: t('admin.shared.direction.buyAndSell'), value: 'all' },
    { label: t('admin.shared.direction.buy'), value: 'buy' },
    { label: t('admin.shared.direction.sell'), value: 'sell' },
]);

const columns = computed(() => [
    { title: t('admin.orders.index.columns.order'), key: 'order' },
    { title: t('admin.orders.index.columns.amount'), key: 'amount', width: 160 },
    { title: t('admin.orders.index.columns.status'), key: 'status', width: 180 },
    { title: t('admin.orders.index.columns.time'), key: 'time', width: 160 },
    { title: '', key: 'actions', width: 90, align: 'right' },
]);

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
    <Head :title="t('admin.orders.index.headTitle')" />

    <AdminLayout>
        <template #title>{{ t('admin.orders.index.title') }}</template>

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
                                    №{{ record.id }} · {{ record.direction === 'buy' ? t('admin.shared.direction.buy') : t('admin.shared.direction.sell') }}
                                </a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.user?.name ?? t('admin.shared.empty') }} · {{ record.user?.phone ?? t('admin.shared.empty') }}
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
                            <div v-if="record.fiat_payment_request?.proof_file_path" class="admin-ant-meta">{{ t('admin.orders.index.screenshot') }}</div>
                        </template>

                        <template v-else-if="column.key === 'time'">
                            <span class="admin-ant-meta">{{ formatDate(record.created_at) }}</span>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="`/admin/orders/${record.id}`">
                                <a-button type="link" size="small">{{ t('admin.shared.actions.open') }}</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.orders.index.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="orders" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
