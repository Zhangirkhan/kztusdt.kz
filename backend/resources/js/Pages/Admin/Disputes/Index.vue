<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    disputes: Array,
    stats: Object,
});

const { t } = useI18n();

const statItems = computed(() => [
    { label: t('admin.disputes.stats.open'), value: props.stats.open, color: '#faad14' },
]);

const columns = computed(() => [
    { title: t('admin.disputes.columns.order'), dataIndex: 'id', key: 'id', width: 90 },
    { title: t('admin.disputes.columns.client'), dataIndex: 'user', key: 'user' },
    { title: t('admin.disputes.columns.direction'), key: 'direction', width: 120 },
    { title: t('admin.disputes.columns.amount'), key: 'amount' },
    { title: t('admin.disputes.columns.status'), key: 'status', width: 140 },
    { title: t('admin.disputes.columns.date'), key: 'date', width: 170 },
    { title: '', key: 'actions', width: 90, align: 'right' },
]);
</script>

<template>
    <Head :title="t('admin.disputes.headTitle')" />

    <AdminLayout>
        <template #title>{{ t('admin.disputes.title') }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="disputes"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'id'">#{{ record.id }}</template>

                        <template v-else-if="column.key === 'direction'">
                            {{ record.direction === 'buy' ? t('admin.shared.direction.buy') : t('admin.shared.direction.sell') }}
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            {{ record.fiat_amount }} ₸ / {{ record.crypto_amount }} USDT
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

                    <template #emptyText>
                        <a-empty :description="t('admin.disputes.empty')" />
                    </template>
                </a-table>
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
