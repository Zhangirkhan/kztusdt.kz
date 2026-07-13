<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminResponsiveTable from '@/shared/ui/admin/AdminResponsiveTable.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    tab: String,
    status: String,
    items: Array,
    stats: Object,
});

const { t } = useI18n();

const statItems = computed(() => [
    { label: t('admin.finance.stats.pendingWithdrawals'), value: props.stats.pending_withdrawals, color: '#faad14' },
    { label: t('admin.finance.stats.pendingDeposits'), value: props.stats.pending_deposits, color: '#1677ff' },
    { label: t('admin.finance.stats.completedWithdrawals'), value: props.stats.completed_withdrawals, color: '#52c41a' },
    { label: t('admin.finance.stats.creditedDeposits'), value: props.stats.credited_deposits, color: '#52c41a' },
]);

const tabOptions = computed(() => [
    { label: t('admin.finance.tabs.withdrawals'), value: 'withdrawals' },
    { label: t('admin.finance.tabs.deposits'), value: 'deposits' },
]);

const statusOptions = computed(() => [
    { label: t('admin.finance.filters.active'), value: 'active' },
    { label: t('admin.finance.filters.completed'), value: 'completed' },
    { label: t('admin.finance.filters.failed'), value: 'failed' },
    { label: t('admin.finance.filters.all'), value: 'all' },
]);

const columns = computed(() => [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: t('admin.finance.columns.type'), key: 'type', width: 100 },
    { title: t('admin.finance.columns.client'), dataIndex: 'user', key: 'user' },
    { title: t('admin.finance.columns.amount'), dataIndex: 'amount', key: 'amount' },
    { title: t('admin.finance.columns.status'), key: 'status', width: 140 },
    { title: t('admin.finance.columns.date'), key: 'date', width: 170 },
    { title: '', key: 'actions', width: 90, align: 'right' },
]);

function reload(params = {}) {
    router.get('/admin/finance', params, { preserveState: true });
}
</script>

<template>
    <Head :title="t('admin.finance.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.finance.title') }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="tab" :options="tabOptions" @change="(v) => reload({ tab: v, status })" />
            <AdminFilters :model-value="status" :options="statusOptions" size="small" @change="(v) => reload({ tab, status: v })" />

            <a-card :bordered="false" size="small">
                <AdminResponsiveTable
                    :columns="columns"
                    :data-source="items"
                    :row-key="(record) => `${record.type}-${record.id}`"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'id'">#{{ record.id }}</template>

                        <template v-else-if="column.key === 'type'">
                            {{ record.type === 'deposit' ? t('admin.finance.types.deposit') : t('admin.finance.types.withdrawal') }}
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
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
                            <a-typography-text strong>
                                #{{ record.id }} · {{ record.type === 'deposit' ? t('admin.finance.types.deposit') : t('admin.finance.types.withdrawal') }}
                            </a-typography-text>
                            <div class="admin-ant-meta">{{ record.user }}</div>
                            <div>{{ record.amount }}</div>
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
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
                        <a-empty :description="t('admin.finance.empty')" />
                    </template>
                </AdminResponsiveTable>
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
