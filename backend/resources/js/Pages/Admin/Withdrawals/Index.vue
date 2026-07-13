<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminResponsiveTable from '@/shared/ui/admin/AdminResponsiveTable.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { networkTagColor, withdrawalStatusTagColor } from '@/shared/lib/admin/tagColors';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    withdrawals: Object,
    filterStatus: String,
    enabled: Boolean,
    autoLimit: Number,
    stats: Object,
});

const { t } = useI18n();

const showRejectModal = ref(false);
const showApproveModal = ref(false);
const rejectForm = useForm({ reason: '' });
const approvingId = ref(null);
const rejectingId = ref(null);

const statusLabels = computed(() => ({
    created: t('admin.withdrawals.status.created'),
    awaiting_telegram_confirmation: t('admin.withdrawals.status.awaiting_telegram_confirmation'),
    pending_review: t('admin.withdrawals.status.pending_review'),
    approved: t('admin.withdrawals.status.approved'),
    sending: t('admin.withdrawals.status.sending'),
    sent: t('admin.withdrawals.status.sent'),
    completed: t('admin.withdrawals.status.completed'),
    cancelled: t('admin.withdrawals.status.cancelled'),
    failed: t('admin.withdrawals.status.failed'),
    rejected: t('admin.withdrawals.status.rejected'),
    needs_reconcile: t('admin.withdrawals.status.needs_reconcile'),
}));

const statItems = computed(() => [
    { label: t('admin.withdrawals.stats.review'), value: props.stats.review, color: '#ff4d4f' },
    { label: t('admin.withdrawals.stats.queued'), value: props.stats.queued, color: '#1677ff' },
    { label: t('admin.withdrawals.stats.completed'), value: props.stats.completed, color: '#52c41a' },
    { label: t('admin.withdrawals.stats.failed'), value: props.stats.failed, color: '#ff4d4f' },
]);

const filterOptions = computed(() => [
    { label: t('admin.withdrawals.filters.review'), value: 'review', count: props.stats.review },
    { label: t('admin.withdrawals.filters.active'), value: 'active', count: props.stats.active },
    { label: t('admin.withdrawals.filters.completed'), value: 'completed', count: props.stats.completed },
    { label: t('admin.withdrawals.filters.failed'), value: 'failed', count: props.stats.failed },
    { label: t('admin.withdrawals.filters.all'), value: 'all', count: props.stats.all },
]);

const columns = computed(() => [
    { title: t('admin.withdrawals.columns.withdrawal'), key: 'withdrawal' },
    { title: t('admin.withdrawals.columns.amount'), key: 'amount', width: 140 },
    { title: t('admin.withdrawals.columns.status'), key: 'status', width: 180 },
    { title: t('admin.withdrawals.columns.actions'), key: 'actions', width: 220 },
]);

function setFilter(status) {
    router.get('/admin/withdrawals', { status }, { preserveState: true });
}

function approve(id) {
    approvingId.value = id;
    showApproveModal.value = true;
}

function confirmApprove() {
    if (!approvingId.value) {
        return;
    }

    router.post(`/admin/withdrawals/${approvingId.value}/approve`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            approvingId.value = null;
            showApproveModal.value = false;
        },
    });
}

function retry(id) {
    router.post(`/admin/withdrawals/${id}/retry`, {}, { preserveScroll: true });
}

function startReject(id) {
    rejectingId.value = id;
    rejectForm.reset();
    showRejectModal.value = true;
}

function reject() {
    if (!rejectingId.value) {
        return;
    }

    rejectForm.post(`/admin/withdrawals/${rejectingId.value}/reject`, {
        preserveScroll: true,
        onSuccess: () => {
            rejectingId.value = null;
            showRejectModal.value = false;
        },
    });
}

function short(value) {
    return value ? `${value.slice(0, 10)}…${value.slice(-8)}` : t('admin.shared.empty');
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU');
}

function explorerTxUrl(network, txHash) {
    if (! txHash) {
        return null;
    }

    if (network === 'TRC20') {
        return `https://tronscan.org/#/transaction/${txHash}`;
    }

    return `https://bscscan.com/tx/${txHash}`;
}
</script>

<template>
    <Head :title="t('admin.withdrawals.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.withdrawals.title') }}</template>

        <AdminPage>
            <a-alert
                v-if="!enabled"
                type="warning"
                show-icon
                :message="t('admin.withdrawals.disabledAlert')"
                class="admin-ant-block"
            />

            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="filterStatus" :options="filterOptions" @change="setFilter" />

            <a-card :bordered="false" size="small">
                <AdminResponsiveTable
                    :columns="columns"
                    :data-source="withdrawals.data"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'withdrawal'">
                            <div>
                                <a-space wrap :size="4">
                                    <a-typography-text strong>№{{ record.id }}</a-typography-text>
                                    <a-tag :color="networkTagColor(record.network)">{{ record.network }}</a-tag>
                                    <a-tag>{{ record.asset }}</a-tag>
                                    <a-tag v-if="record.requires_manual_approval" color="error">{{ t('admin.withdrawals.meta.compliance') }}</a-tag>
                                </a-space>
                                <div class="admin-ant-meta">
                                    {{ record.user?.name ?? t('admin.shared.empty') }} · {{ record.user?.phone ?? t('admin.shared.empty') }}
                                </div>
                                <div class="admin-ant-meta">
                                    {{ t('admin.withdrawals.meta.created', { date: formatDate(record.created_at) }) }}
                                    <template v-if="record.completed_at">
                                        {{ t('admin.withdrawals.meta.completed', { date: formatDate(record.completed_at) }) }}
                                    </template>
                                </div>
                                <a-typography-text code class="admin-ant-meta">{{ record.to_address }}</a-typography-text>
                                <div v-if="record.tx_hash">
                                    <a :href="explorerTxUrl(record.network, record.tx_hash)" target="_blank" rel="noopener">
                                        <a-button type="link" size="small" style="padding-left: 0">
                                            tx: {{ short(record.tx_hash) }}
                                        </a-button>
                                    </a>
                                </div>
                                <a-typography-text v-if="record.last_error" type="danger" class="admin-ant-meta">
                                    {{ record.last_error }}
                                </a-typography-text>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            <a-typography-text strong>{{ formatUsdt(record.amount, 2) }}</a-typography-text>
                            <div class="admin-ant-meta">{{ t('admin.withdrawals.meta.debit', { amount: formatUsdt(record.total_debit, 4) }) }}</div>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="withdrawalStatusTagColor(record.status)">
                                {{ statusLabels[record.status] ?? record.status }}
                            </a-tag>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <a-space v-if="record.status === 'failed'" wrap>
                                <a-popconfirm :title="t('admin.withdrawals.retryConfirm')" :ok-text="t('admin.shared.actions.yes')" :cancel-text="t('admin.shared.actions.no')" @confirm="retry(record.id)">
                                    <a-button type="primary" size="small">{{ t('admin.shared.actions.retry') }}</a-button>
                                </a-popconfirm>
                            </a-space>

                            <a-space v-else-if="record.status === 'pending_review'" wrap>
                                <a-button type="primary" size="small" @click="approve(record.id)">
                                    {{ t('admin.shared.actions.approve') }}
                                </a-button>
                                <a-button danger size="small" @click="startReject(record.id)">{{ t('admin.shared.actions.reject') }}</a-button>
                            </a-space>
                        </template>
                    </template>

                    <template #mobile="{ record }">
                        <div>
                            <a-space wrap :size="4">
                                <a-typography-text strong>№{{ record.id }}</a-typography-text>
                                <a-tag :color="networkTagColor(record.network)">{{ record.network }}</a-tag>
                                <a-tag>{{ record.asset }}</a-tag>
                                <a-tag v-if="record.requires_manual_approval" color="error">{{ t('admin.withdrawals.meta.compliance') }}</a-tag>
                            </a-space>
                            <div class="admin-ant-meta">
                                {{ record.user?.name ?? t('admin.shared.empty') }} · {{ record.user?.phone ?? t('admin.shared.empty') }}
                            </div>
                            <div class="admin-ant-meta">
                                {{ t('admin.withdrawals.meta.created', { date: formatDate(record.created_at) }) }}
                                <template v-if="record.completed_at">
                                    {{ t('admin.withdrawals.meta.completed', { date: formatDate(record.completed_at) }) }}
                                </template>
                            </div>
                            <a-typography-text code class="admin-ant-meta">{{ record.to_address }}</a-typography-text>
                            <div v-if="record.tx_hash">
                                <a :href="explorerTxUrl(record.network, record.tx_hash)" target="_blank" rel="noopener">
                                    <a-button type="link" size="small" style="padding-left: 0">
                                        tx: {{ short(record.tx_hash) }}
                                    </a-button>
                                </a>
                            </div>
                            <a-typography-text v-if="record.last_error" type="danger" class="admin-ant-meta">
                                {{ record.last_error }}
                            </a-typography-text>
                        </div>
                        <div>
                            <a-typography-text strong>{{ formatUsdt(record.amount, 2) }}</a-typography-text>
                            <div class="admin-ant-meta">{{ t('admin.withdrawals.meta.debit', { amount: formatUsdt(record.total_debit, 4) }) }}</div>
                        </div>
                        <a-tag :color="withdrawalStatusTagColor(record.status)">
                            {{ statusLabels[record.status] ?? record.status }}
                        </a-tag>
                        <div v-if="record.status === 'failed'" class="admin-responsive-table__actions">
                            <a-popconfirm :title="t('admin.withdrawals.retryConfirm')" :ok-text="t('admin.shared.actions.yes')" :cancel-text="t('admin.shared.actions.no')" @confirm="retry(record.id)">
                                <a-button type="primary" block>{{ t('admin.shared.actions.retry') }}</a-button>
                            </a-popconfirm>
                        </div>
                        <div v-else-if="record.status === 'pending_review'" class="admin-responsive-table__actions">
                            <a-space direction="vertical" style="width: 100%">
                                <a-button type="primary" block @click="approve(record.id)">
                                    {{ t('admin.shared.actions.approve') }}
                                </a-button>
                                <a-button danger block @click="startReject(record.id)">{{ t('admin.shared.actions.reject') }}</a-button>
                            </a-space>
                        </div>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.withdrawals.empty')" />
                    </template>
                </AdminResponsiveTable>

                <AdminPagination :pagination="withdrawals" />
            </a-card>

            <a-modal
                v-model:open="showApproveModal"
                :title="t('admin.withdrawals.modals.approve.title')"
                :ok-text="t('admin.shared.actions.approve')"
                :cancel-text="t('admin.shared.actions.cancel')"
                @ok="confirmApprove"
                @cancel="approvingId = null"
            >
                <a-typography-text>
                    {{ t('admin.withdrawals.modals.approve.body', { id: approvingId }) }}
                </a-typography-text>
            </a-modal>

            <a-modal
                v-model:open="showRejectModal"
                :title="t('admin.withdrawals.modals.reject.title')"
                :ok-text="t('admin.shared.actions.confirm')"
                :cancel-text="t('admin.shared.actions.cancel')"
                ok-type="danger"
                :confirm-loading="rejectForm.processing"
                destroy-on-close
                @ok="reject"
                @cancel="rejectingId = null"
            >
                <a-form layout="vertical">
                    <a-form-item :label="t('admin.withdrawals.modals.reject.reasonLabel')" required>
                        <a-textarea v-model:value="rejectForm.reason" :rows="3" :placeholder="t('admin.withdrawals.modals.reject.reasonPlaceholder')" />
                    </a-form-item>
                </a-form>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>
