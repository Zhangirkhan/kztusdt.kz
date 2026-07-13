<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminResponsiveTable from '@/shared/ui/admin/AdminResponsiveTable.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    sweeps: Object,
    filterStatus: String,
    stats: Object,
    enabled: Boolean,
});

const { t } = useI18n();

const statItems = computed(() => [
    { label: t('admin.sweeps.stats.waitingGas'), value: props.stats.waiting_gas, color: '#faad14' },
    { label: t('admin.sweeps.stats.inProgress'), value: props.stats.in_progress, color: '#1677ff' },
    { label: t('admin.sweeps.stats.swept'), value: props.stats.swept, color: '#52c41a' },
    { label: t('admin.sweeps.stats.attention'), value: props.stats.attention, color: '#ff4d4f' },
]);

const filterOptions = computed(() => [
    { label: t('admin.sweeps.filters.active'), value: 'active' },
    { label: t('admin.sweeps.filters.swept'), value: 'swept' },
    { label: t('admin.sweeps.filters.attention'), value: 'attention' },
    { label: t('admin.sweeps.filters.all'), value: 'all' },
]);

const columns = computed(() => [
    { title: t('admin.sweeps.columns.sweep'), key: 'sweep' },
    { title: t('admin.sweeps.columns.amount'), key: 'amount', width: 140 },
    { title: t('admin.sweeps.columns.status'), key: 'status', width: 140 },
    { title: '', key: 'actions', width: 110, align: 'right' },
]);

function setFilter(status) {
    router.get('/admin/sweeps', { status }, { preserveState: true });
}

function retry(id) {
    router.post(`/admin/sweeps/${id}/retry`, {}, { preserveScroll: true });
}

function short(hash) {
    return hash ? `${hash.slice(0, 8)}…${hash.slice(-6)}` : t('admin.shared.empty');
}
</script>

<template>
    <Head :title="t('admin.sweeps.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.sweeps.title') }}</template>

        <AdminPage>
            <a-alert
                v-if="!enabled"
                type="warning"
                show-icon
                :message="t('admin.sweeps.disabledAlert')"
                class="admin-ant-block"
            />

            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="filterStatus" :options="filterOptions" @change="setFilter" />

            <a-card :bordered="false" size="small">
                <AdminResponsiveTable
                    :columns="columns"
                    :data-source="sweeps.data"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'sweep'">
                            <div>
                                <a-typography-text strong>#{{ record.id }} · {{ record.asset }}</a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.user?.phone ?? t('admin.shared.empty') }} · {{ short(record.from_address) }} → {{ short(record.to_address) }}
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
                                {{ t('admin.shared.actions.retry') }}
                            </a-button>
                        </template>
                    </template>

                    <template #mobile="{ record }">
                        <div>
                            <a-typography-text strong>#{{ record.id }} · {{ record.asset }}</a-typography-text>
                            <div class="admin-ant-meta">
                                {{ record.user?.phone ?? t('admin.shared.empty') }} · {{ short(record.from_address) }} → {{ short(record.to_address) }}
                            </div>
                            <a-typography-text v-if="record.last_error" type="danger" class="admin-ant-meta">
                                {{ record.last_error }}
                            </a-typography-text>
                        </div>
                        <a-typography-text strong>{{ formatUsdt(record.amount, 8) }}</a-typography-text>
                        <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        <div v-if="['manual_review', 'failed'].includes(record.status)" class="admin-responsive-table__actions">
                            <a-button type="primary" block @click="retry(record.id)">
                                {{ t('admin.shared.actions.retry') }}
                            </a-button>
                        </div>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.sweeps.empty')" />
                    </template>
                </AdminResponsiveTable>

                <AdminPagination :pagination="sweeps" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
