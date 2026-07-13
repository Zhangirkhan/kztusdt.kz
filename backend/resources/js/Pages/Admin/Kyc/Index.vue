<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    profiles: Object,
    filterStatus: String,
    stats: Object,
    sumsubAdminEnabled: { type: Boolean, default: false },
});

const { t } = useI18n();

const statItems = computed(() => [
    { label: t('admin.kyc.index.stats.pending'), value: props.stats.pending, color: '#faad14' },
    { label: t('admin.kyc.index.stats.approved'), value: props.stats.approved, color: '#52c41a' },
    { label: t('admin.kyc.index.stats.rejected'), value: props.stats.rejected, color: '#ff4d4f' },
]);

const filterOptions = computed(() => [
    { label: t('admin.kyc.index.filters.pending', { count: props.stats.pending }), value: 'pending_review' },
    { label: t('admin.kyc.index.filters.approved', { count: props.stats.approved }), value: 'approved' },
    { label: t('admin.kyc.index.filters.rejected', { count: props.stats.rejected }), value: 'rejected' },
    { label: t('admin.kyc.index.filters.all'), value: 'all' },
]);

const columns = computed(() => [
    { title: t('admin.kyc.index.columns.client'), key: 'client' },
    { title: t('admin.kyc.index.columns.document'), key: 'document' },
    { title: t('admin.kyc.index.columns.status'), key: 'status', width: 140 },
    { title: '', key: 'actions', width: 90, align: 'right' },
]);

function setFilter(status) {
    router.get('/admin/kyc', { status }, { preserveState: true });
}
</script>

<template>
    <Head :title="t('admin.kyc.index.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.kyc.index.title') }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="filterStatus" :options="filterOptions" @change="setFilter" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="profiles.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'client'">
                            <div>
                                <a-typography-text strong>
                                    {{ [record.first_name, record.last_name].filter(Boolean).join(' ') || record.user?.name || `User #${record.user?.id}` }}
                                </a-typography-text>
                                <div class="admin-ant-meta">{{ record.user?.phone ?? t('admin.shared.empty') }}</div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'document'">
                            <a-tag v-if="sumsubAdminEnabled && record.provider === 'sumsub'" color="blue">Sumsub</a-tag>
                            <span v-else-if="record.document_type || record.document_number">
                                {{ record.document_type }} {{ record.document_number }}
                            </span>
                            <span v-else class="admin-ant-meta">{{ t('admin.shared.empty') }}</span>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="`/admin/kyc/${record.id}`">
                                <a-button type="link" size="small">{{ t('admin.shared.actions.open') }}</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.kyc.index.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="profiles" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
