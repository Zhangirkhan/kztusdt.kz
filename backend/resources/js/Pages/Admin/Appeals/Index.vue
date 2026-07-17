<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminResponsiveTable from '@/shared/ui/admin/AdminResponsiveTable.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    appeals: Object,
    filterStatus: String,
    filterSide: String,
    stats: Object,
});

const { t } = useI18n();

const statItems = computed(() => [
    { label: t('admin.appeals.stats.open'), value: props.stats.open, color: '#faad14' },
    { label: t('admin.appeals.stats.total'), value: props.stats.total, color: '#1677ff' },
]);

const statusFilterOptions = computed(() => [
    { label: t('admin.appeals.filters.open', { count: props.stats.open }), value: 'open' },
    { label: t('admin.appeals.filters.all'), value: 'all' },
]);

const sideFilterOptions = computed(() => [
    { label: t('admin.appeals.filters.sideClient'), value: 'client' },
    { label: t('admin.appeals.filters.sideExchange'), value: 'exchange' },
    { label: t('admin.appeals.filters.sideAll'), value: 'all' },
]);

const columns = computed(() => [
    { title: t('admin.appeals.columns.order'), dataIndex: 'order_id', key: 'order_id', width: 90 },
    { title: t('admin.appeals.columns.client'), dataIndex: 'client', key: 'client' },
    { title: t('admin.appeals.columns.side'), key: 'side', width: 130 },
    { title: t('admin.appeals.columns.reason'), key: 'reason' },
    { title: t('admin.appeals.columns.amount'), key: 'amount' },
    { title: t('admin.appeals.columns.status'), key: 'status', width: 120 },
    { title: t('admin.appeals.columns.date'), key: 'date', width: 170 },
    { title: '', key: 'actions', width: 90, align: 'right' },
]);

function setStatusFilter(status) {
    router.get('/admin/appeals', { status, side: props.filterSide }, { preserveState: true });
}

function setSideFilter(side) {
    router.get('/admin/appeals', { status: props.filterStatus, side }, { preserveState: true });
}

function reasonLabel(reason) {
    return t(`admin.appeals.reasons.${reason}`, reason);
}

function sideLabel(side) {
    return t(`admin.appeals.sides.${side}`, side);
}

function statusLabel(status) {
    return t(`admin.appeals.statuses.${status}`, status);
}
</script>

<template>
    <Head :title="t('admin.appeals.headTitle')" />

    <AdminLayout>
        <template #title>{{ t('admin.appeals.title') }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <div class="mb-3 flex flex-wrap gap-3">
                <AdminFilters :model-value="filterStatus" :options="statusFilterOptions" @change="setStatusFilter" />
                <AdminFilters :model-value="filterSide" :options="sideFilterOptions" @change="setSideFilter" />
            </div>

            <a-card :bordered="false" size="small">
                <AdminResponsiveTable
                    :columns="columns"
                    :data-source="appeals.data"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'order_id'">#{{ record.order_id }}</template>

                        <template v-else-if="column.key === 'side'">
                            <a-tag>{{ sideLabel(record.side) }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'reason'">
                            {{ reasonLabel(record.reason) }}
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            {{ record.fiat_amount }} ₸ / {{ record.crypto_amount }} USDT
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ statusLabel(record.status) }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'date'">
                            {{ record.created_at ? new Date(record.created_at).toLocaleString('ru-RU') : t('admin.shared.empty') }}
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="record.href">
                                <a-button type="link" size="small">{{ t('admin.shared.actions.open') }}</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #mobile="{ record }">
                        <div>
                            <a-typography-text strong>#{{ record.order_id }}</a-typography-text>
                            <div class="admin-ant-meta">{{ record.client }}</div>
                            <a-tag>{{ sideLabel(record.side) }}</a-tag>
                            <div>{{ reasonLabel(record.reason) }}</div>
                            <div>{{ record.fiat_amount }} ₸ / {{ record.crypto_amount }} USDT</div>
                            <a-tag :color="statusTagColor(record.status)">{{ statusLabel(record.status) }}</a-tag>
                            <div class="admin-ant-meta">
                                {{ record.created_at ? new Date(record.created_at).toLocaleString('ru-RU') : t('admin.shared.empty') }}
                            </div>
                        </div>
                        <div class="admin-responsive-table__actions">
                            <Link :href="record.href">
                                <a-button type="primary" block>{{ t('admin.shared.actions.open') }}</a-button>
                            </Link>
                        </div>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.appeals.empty')" />
                    </template>
                </AdminResponsiveTable>

                <AdminPagination :paginator="appeals" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
