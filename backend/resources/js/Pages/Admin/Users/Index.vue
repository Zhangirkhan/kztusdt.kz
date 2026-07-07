<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { clientTypeTagColor, statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    users: Object,
    filters: Object,
    stats: Object,
});

const search = ref(props.filters?.q ?? '');
const statusFilter = ref(props.filters?.status ?? 'all');
const clientTypeFilter = ref(props.filters?.client_type ?? 'all');

const statItems = computed(() => [
    { label: 'Всего', value: props.stats.total, color: '#1677ff' },
    { label: 'Активные', value: props.stats.active, color: '#52c41a' },
    { label: 'Приостановлены', value: props.stats.suspended, color: '#faad14' },
]);

const statusOptions = [
    { label: 'Все', value: 'all' },
    { label: 'Активные', value: 'active' },
    { label: 'Приостановлены', value: 'suspended' },
    { label: 'Заблокированы', value: 'blocked' },
];

const clientTypeOptions = [
    { label: 'Все клиенты', value: 'all' },
    { label: 'Физ. лица', value: 'individual' },
    { label: 'Юр. лица', value: 'legal_entity' },
];

const columns = [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 70 },
    { title: 'Тип', key: 'client_type', width: 110 },
    { title: 'Клиент', key: 'client' },
    { title: 'KYC', dataIndex: 'kyc_status', key: 'kyc_status', width: 120 },
    { title: 'Статус', key: 'status', width: 120 },
    { title: 'Активность', key: 'activity', width: 160 },
    { title: '', key: 'actions', width: 90, align: 'right' },
];

function applyFilters(status = statusFilter.value, clientType = clientTypeFilter.value) {
    statusFilter.value = status;
    clientTypeFilter.value = clientType;
    router.get('/admin/users', { q: search.value, status, client_type: clientType }, { preserveState: true });
}

function submitSearch() {
    applyFilters('all', clientTypeFilter.value);
}

function clientTypeLabel(type) {
    return type === 'legal_entity' ? 'Юр. лицо' : 'Физ. лицо';
}
</script>

<template>
    <Head title="Пользователи" />

    <AdminLayout>
        <template #title>Пользователи</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <a-input-search
                v-model:value="search"
                placeholder="Поиск: имя, телефон, email, ID"
                enter-button="Найти"
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
                                <a-typography-text strong>{{ record.company_name || record.name || '—' }}</a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.phone || record.email }}
                                    <template v-if="record.client_type === 'legal_entity' && record.bin"> · БИН {{ record.bin }}</template>
                                    <template v-else-if="record.iin"> · ИИН {{ record.iin }}</template>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">{{ record.status }}</a-tag>
                        </template>

                        <template v-else-if="column.key === 'activity'">
                            <span class="admin-ant-meta">
                                {{ record.exchange_orders_count }} орд. ·
                                {{ record.withdrawals_count }} выв. ·
                                {{ record.deposits_count }} деп.
                            </span>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <Link :href="`/admin/users/${record.id}`">
                                <a-button type="link" size="small">Открыть</a-button>
                            </Link>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty description="Пользователей не найдено" />
                    </template>
                </a-table>

                <AdminPagination :pagination="users" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
