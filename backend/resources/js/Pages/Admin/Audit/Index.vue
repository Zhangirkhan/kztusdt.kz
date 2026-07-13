<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    logs: Object,
    filters: Object,
});

const { t } = useI18n();

const search = ref(props.filters?.q ?? '');

const columns = computed(() => [
    { title: 'ID', dataIndex: 'id', key: 'id', width: 80 },
    { title: 'Action', key: 'action' },
    { title: 'Entity', key: 'entity' },
    { title: t('admin.audit.columns.user'), key: 'user' },
    { title: t('admin.audit.columns.date'), key: 'date', width: 180 },
]);

function submitSearch() {
    router.get('/admin/audit', { q: search.value }, { preserveState: true });
}
</script>

<template>
    <Head :title="t('admin.audit.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.audit.title') }}</template>

        <AdminPage>
            <a-input-search
                v-model:value="search"
                :placeholder="t('admin.audit.searchPlaceholder')"
                :enter-button="t('admin.shared.actions.find')"
                size="large"
                class="admin-ant-block"
                @search="submitSearch"
            />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="logs.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'id'">#{{ record.id }}</template>

                        <template v-else-if="column.key === 'action'">
                            <a-typography-text code>{{ record.action }}</a-typography-text>
                        </template>

                        <template v-else-if="column.key === 'entity'">
                            {{ record.entity_type }} #{{ record.entity_id }}
                        </template>

                        <template v-else-if="column.key === 'user'">
                            {{ record.user?.name || record.user?.email || t('admin.shared.empty') }}
                        </template>

                        <template v-else-if="column.key === 'date'">
                            {{ record.created_at ? new Date(record.created_at).toLocaleString('ru-RU') : t('admin.shared.empty') }}
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.audit.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="logs" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
