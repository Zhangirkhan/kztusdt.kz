<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    profiles: Object,
    filterStatus: String,
    stats: Object,
    sumsubAdminEnabled: { type: Boolean, default: false },
});

const statItems = computed(() => [
    { label: 'На проверке', value: props.stats.pending, color: '#faad14' },
    { label: 'Одобрено', value: props.stats.approved, color: '#52c41a' },
    { label: 'Отклонено', value: props.stats.rejected, color: '#ff4d4f' },
]);

const filterOptions = computed(() => [
    { label: `На проверке (${props.stats.pending})`, value: 'pending_review' },
    { label: `Одобрено (${props.stats.approved})`, value: 'approved' },
    { label: `Отклонено (${props.stats.rejected})`, value: 'rejected' },
    { label: 'Все', value: 'all' },
]);

const columns = [
    { title: 'Клиент', key: 'client' },
    { title: 'Документ', key: 'document' },
    { title: 'Статус', key: 'status', width: 140 },
    { title: '', key: 'actions', width: 90, align: 'right' },
];

function setFilter(status) {
    router.get('/admin/kyc', { status }, { preserveState: true });
}
</script>

<template>
    <Head title="KYC Admin" />

    <AdminLayout>
        <template #title>KYC / верификация</template>

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
                                <div class="admin-ant-meta">{{ record.user?.phone ?? '—' }}</div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'document'">
                            <a-tag v-if="sumsubAdminEnabled && record.provider === 'sumsub'" color="blue">Sumsub</a-tag>
                            <span v-else-if="record.document_type || record.document_number">
                                {{ record.document_type }} {{ record.document_number }}
                            </span>
                            <span v-else class="admin-ant-meta">—</span>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="`/admin/kyc/${record.id}`">
                                <a-button type="link" size="small">Открыть</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty description="Заявок нет" />
                    </template>
                </a-table>

                <AdminPagination :pagination="profiles" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
