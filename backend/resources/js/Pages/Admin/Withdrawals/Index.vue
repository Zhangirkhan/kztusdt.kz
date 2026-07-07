<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { networkTagColor, withdrawalStatusTagColor } from '@/shared/lib/admin/tagColors';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    withdrawals: Object,
    filterStatus: String,
    enabled: Boolean,
    autoLimit: Number,
    stats: Object,
});

const showRejectModal = ref(false);
const showApproveModal = ref(false);
const rejectForm = useForm({ reason: '' });
const approvingId = ref(null);
const rejectingId = ref(null);

const statusLabels = {
    created: 'Создана',
    awaiting_telegram_confirmation: 'Ждёт подтверждения',
    pending_review: 'Ждёт проверки СБ',
    approved: 'Одобрена, в очереди',
    sending: 'Отправляется',
    sent: 'Отправлена, ждём сеть',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    rejected: 'Отклонена',
    needs_reconcile: 'Сверка',
};

const statItems = computed(() => [
    { label: 'Ждут проверки СБ', value: props.stats.review, color: '#ff4d4f' },
    { label: 'В очереди / сети', value: props.stats.queued, color: '#1677ff' },
    { label: 'Выполнено', value: props.stats.completed, color: '#52c41a' },
    { label: 'Ошибки', value: props.stats.failed, color: '#ff4d4f' },
]);

const filterOptions = computed(() => [
    { label: 'Проверка СБ', value: 'review', count: props.stats.review },
    { label: 'Активные', value: 'active', count: props.stats.active },
    { label: 'Выполненные', value: 'completed', count: props.stats.completed },
    { label: 'Ошибки', value: 'failed', count: props.stats.failed },
    { label: 'Все', value: 'all', count: props.stats.all },
]);

const columns = [
    { title: 'Вывод', key: 'withdrawal' },
    { title: 'Сумма', key: 'amount', width: 140 },
    { title: 'Статус', key: 'status', width: 180 },
    { title: 'Действия', key: 'actions', width: 220 },
];

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
    return value ? `${value.slice(0, 10)}…${value.slice(-8)}` : '—';
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
    <Head title="Выводы" />

    <AdminLayout>
        <template #title>Выводы USDT</template>

        <AdminPage>
            <a-alert
                v-if="!enabled"
                type="warning"
                show-icon
                message="Отправка выключена (WITHDRAWALS_ENABLED=false). Одобренные заявки копятся в очереди."
                class="admin-ant-block"
            />

            <AdminStatsRow :items="statItems" />

            <AdminFilters :model-value="filterStatus" :options="filterOptions" @change="setFilter" />

            <a-card :bordered="false" size="small">
                <a-table
                    :columns="columns"
                    :data-source="withdrawals.data"
                    :pagination="false"
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
                                    <a-tag v-if="record.requires_manual_approval" color="error">СБ</a-tag>
                                </a-space>
                                <div class="admin-ant-meta">
                                    {{ record.user?.name ?? '—' }} · {{ record.user?.phone ?? '—' }}
                                </div>
                                <div class="admin-ant-meta">
                                    Создан: {{ formatDate(record.created_at) }}
                                    <template v-if="record.completed_at">
                                        · Выполнен: {{ formatDate(record.completed_at) }}
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
                            <div class="admin-ant-meta">списание {{ formatUsdt(record.total_debit, 4) }}</div>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="withdrawalStatusTagColor(record.status)">
                                {{ statusLabels[record.status] ?? record.status }}
                            </a-tag>
                        </template>

                        <template v-else-if="column.key === 'actions'">
                            <a-space v-if="record.status === 'failed'" wrap>
                                <a-popconfirm title="Повторить отправку?" ok-text="Да" cancel-text="Нет" @confirm="retry(record.id)">
                                    <a-button type="primary" size="small">Повторить</a-button>
                                </a-popconfirm>
                            </a-space>

                            <a-space v-else-if="record.status === 'pending_review'" wrap>
                                <a-button type="primary" size="small" @click="approve(record.id)">
                                    Одобрить
                                </a-button>
                                <a-button danger size="small" @click="startReject(record.id)">Отклонить</a-button>
                            </a-space>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty description="Нет записей" />
                    </template>
                </a-table>

                <AdminPagination :pagination="withdrawals" />
            </a-card>

            <a-modal
                v-model:open="showApproveModal"
                title="Одобрить вывод"
                ok-text="Одобрить"
                cancel-text="Отмена"
                @ok="confirmApprove"
                @cancel="approvingId = null"
            >
                <a-typography-text>
                    Одобрить вывод №{{ approvingId }}? Заявка попадёт в очередь на отправку.
                </a-typography-text>
            </a-modal>

            <a-modal
                v-model:open="showRejectModal"
                title="Отклонить вывод"
                ok-text="Подтвердить"
                cancel-text="Отмена"
                ok-type="danger"
                :confirm-loading="rejectForm.processing"
                destroy-on-close
                @ok="reject"
                @cancel="rejectingId = null"
            >
                <a-form layout="vertical">
                    <a-form-item label="Причина отклонения" required>
                        <a-textarea v-model:value="rejectForm.reason" :rows="3" placeholder="Причина отклонения" />
                    </a-form-item>
                </a-form>
            </a-modal>
        </AdminPage>
    </AdminLayout>
</template>
