<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { clientTypeTagColor, statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
    stats: Object,
});

const { t } = useI18n();

const search = ref(props.filters?.q ?? '');
const statusFilter = ref(props.filters?.status ?? 'all');
const clientTypeFilter = ref(props.filters?.client_type ?? 'all');

const statItems = computed(() => [
    { label: t('admin.users.index.stats.total'), value: props.stats.total, color: '#1677ff' },
    { label: t('admin.users.index.stats.active'), value: props.stats.active, color: '#52c41a' },
    { label: t('admin.users.index.stats.suspended'), value: props.stats.suspended, color: '#faad14' },
]);

const statusOptions = computed(() => [
    { label: t('admin.users.index.filters.status.all'), value: 'all' },
    { label: t('admin.users.index.filters.status.active'), value: 'active' },
    { label: t('admin.users.index.filters.status.suspended'), value: 'suspended' },
    { label: t('admin.users.index.filters.status.blocked'), value: 'blocked' },
]);

const clientTypeOptions = computed(() => [
    { label: t('admin.users.index.filters.clientType.all'), value: 'all' },
    { label: t('admin.users.index.filters.clientType.individual'), value: 'individual' },
    { label: t('admin.users.index.filters.clientType.legalEntity'), value: 'legal_entity' },
]);

const columns = computed(() => [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    { title: t('admin.users.index.columns.type'), key: 'client_type', width: 110 },
    { title: t('admin.users.index.columns.client'), key: 'client' },
    { title: 'KYC', dataIndex: 'kyc_status', key: 'kyc_status', width: 120 },
    { title: t('admin.users.index.columns.status'), key: 'status', width: 120 },
    { title: t('admin.users.index.columns.activity'), key: 'activity', width: 160 },
    { title: '', key: 'actions', width: 90, align: 'right' },
]);

function applyFilters(status = statusFilter.value, clientType = clientTypeFilter.value) {
    statusFilter.value = status;
    clientTypeFilter.value = clientType;
    router.get('/admin/users', { q: search.value, status, client_type: clientType }, { preserveState: true });
}

function submitSearch() {
    applyFilters('all', clientTypeFilter.value);
}

function clientTypeLabel(type) {
    return type === 'legal_entity'
        ? t('admin.users.index.clientType.legalEntity')
        : t('admin.users.index.clientType.individual');
}
</script>

<template>
    <Head :title="t('admin.users.index.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.users.index.title') }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <a-input-search
                v-model:value="search"
                :placeholder="t('admin.users.index.searchPlaceholder')"
                :enter-button="t('admin.shared.actions.find')"
                size="large"
                class="admin-ant-block"
                @search="submitSearch"
            />

            <AdminFilters :model-value="statusFilter" :options="statusOptions" @change="(v) => applyFilters(v)" />
            <AdminFilters :model-value="clientTypeFilter" :options="clientTypeOptions" size="small" @change="(v) => applyFilters(statusFilter, v)" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="users.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'id'">#{{ record.id }}</template>

                        <template v-else-if="column.key === 'client_type'">
                            <a-tag :color="clientTypeTagColor(record.client_type)">
                                {{ clientTypeLabel(record.client_type) }}
                            </a-tag>
                        </template>

                        <template v-else-if="column.key === 'client'">
                            <div>
                                <a-typography-text strong>{{ record.company_name || record.name || t('admin.shared.empty') }}</a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.phone || record.email }}
                                    <template v-if="record.client_type === 'legal_entity' && record.bin"> · {{ t('admin.users.index.meta.bin', { bin: record.bin }) }}</template>
                                    <template v-else-if="record.iin"> · {{ t('admin.users.index.meta.iin', { iin: record.iin }) }}</template>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'activity'">
                            <span class="admin-ant-meta">
                                {{ t('admin.users.index.activity', { orders: record.exchange_orders_count, withdrawals: record.withdrawals_count, deposits: record.deposits_count }) }}
                            </span>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="`/admin/users/${record.id}`">
                                <a-button type="link" size="small">{{ t('admin.shared.actions.open') }}</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.users.index.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="users" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
