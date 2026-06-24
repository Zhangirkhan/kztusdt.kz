<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    withdrawals: Object,
    filterStatus: String,
    enabled: Boolean,
    autoLimit: Number,
    stats: Object,
});

const page = usePage();
const rejectingId = ref(null);
const rejectForm = useForm({ reason: '' });

const statusLabels = {
    created: 'Создана',
    awaiting_telegram_confirmation: 'Ждёт Telegram',
    pending_review: 'Ждёт проверки СБ',
    approved: 'Одобрена, в очереди',
    sending: 'Отправляется',
    sent: 'Отправлена, ждём сеть',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    rejected: 'Отклонена',
    needs_reconcile: 'Сверка (отправка прервана)',
};

const statusColors = {
    pending_review: 'text-red-400',
    approved: 'text-sky-400',
    sending: 'text-sky-400',
    sent: 'text-sky-400',
    completed: 'text-accent',
    failed: 'text-red-400',
    rejected: 'text-red-400',
    needs_reconcile: 'text-amber-400',
};

function setFilter(status) {
    router.get('/admin/withdrawals', { status }, { preserveState: true });
}

function approve(id) {
    if (confirm('Одобрить вывод? Заявка попадёт в очередь на отправку.')) {
        router.post(`/admin/withdrawals/${id}/approve`, {}, { preserveScroll: true });
    }
}

function retry(id) {
    if (confirm('Повторить отправку? Завышенная комиссия сети будет скорректирована, заявка вернётся в очередь.')) {
        router.post(`/admin/withdrawals/${id}/retry`, {}, { preserveScroll: true });
    }
}

function startReject(id) {
    rejectingId.value = id;
    rejectForm.reset();
}

function reject(id) {
    rejectForm.post(`/admin/withdrawals/${id}/reject`, {
        preserveScroll: true,
        onSuccess: () => (rejectingId.value = null),
    });
}

function short(value) {
    return value ? `${value.slice(0, 10)}…${value.slice(-8)}` : '—';
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU');
}
</script>

<template>
    <Head title="Выводы" />

    <AdminLayout>
        <template #title>Выводы USDT</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>
        <p v-if="page.props.errors?.form" class="mb-4 text-sm text-red-400">{{ page.props.errors.form }}</p>

        <div v-if="!enabled" class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-300">
            Отправка выключена (WITHDRAWALS_ENABLED=false). Одобренные заявки копятся в очереди и будут отправлены после включения.
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card">
                <p class="text-body-sm text-text-dim">Ждут проверки СБ</p>
                <p class="text-headline-md text-red-400">{{ stats.review }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">В очереди / сети</p>
                <p class="text-headline-md text-sky-400">{{ stats.queued }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Выполнено</p>
                <p class="text-headline-md text-accent">{{ stats.completed }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Ошибки</p>
                <p class="text-headline-md text-red-400">{{ stats.failed }}</p>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="item in [
                    { key: 'review', label: 'Проверка СБ' },
                    { key: 'active', label: 'Активные' },
                    { key: 'completed', label: 'Выполненные' },
                    { key: 'failed', label: 'Ошибки' },
                    { key: 'all', label: 'Все' },
                ]"
                :key="item.key"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                :class="filterStatus === item.key ? 'bg-accent text-on-accent' : 'bg-surface-container text-text-dim'"
                @click="setFilter(item.key)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="space-y-3">
            <div v-for="withdrawal in withdrawals.data" :key="withdrawal.id" class="card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">
                            №{{ withdrawal.id }} · {{ formatUsdt(withdrawal.amount, 2) }} {{ withdrawal.asset }}
                            <span v-if="withdrawal.requires_manual_approval" class="ml-1 rounded bg-red-500/20 px-2 py-0.5 text-xs text-red-400">
                                СБ
                            </span>
                        </p>
                        <p class="mt-1 text-body-sm text-text-muted">
                            {{ withdrawal.user?.name ?? '—' }} · {{ withdrawal.user?.phone ?? '—' }} · {{ formatDate(withdrawal.created_at) }}
                        </p>
                        <p class="mt-1 font-mono text-xs text-text-dim">→ {{ withdrawal.to_address }}</p>
                        <a
                            v-if="withdrawal.tx_hash"
                            :href="`https://bscscan.com/tx/${withdrawal.tx_hash}`"
                            target="_blank"
                            class="mt-1 block text-xs text-accent"
                        >
                            tx: {{ short(withdrawal.tx_hash) }}
                        </a>
                        <p v-if="withdrawal.last_error" class="mt-1 text-xs text-red-400">{{ withdrawal.last_error }}</p>
                        <p v-if="withdrawal.reject_reason" class="mt-1 text-xs text-red-400">{{ withdrawal.reject_reason }}</p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold uppercase" :class="statusColors[withdrawal.status] ?? 'text-amber-400'">
                            {{ statusLabels[withdrawal.status] ?? withdrawal.status }}
                        </span>
                        <p class="mt-1 text-xs text-text-dim">
                            на адрес: {{ formatUsdt(withdrawal.amount, 4) }} USDT
                        </p>
                        <p class="mt-0.5 text-xs text-text-dim">
                            комиссия сервиса {{ formatUsdt(withdrawal.fee_amount, 4) }}
                            + сеть {{ formatUsdt(withdrawal.network_fee, 4) }}
                            · списание {{ formatUsdt(withdrawal.total_debit, 4) }}
                        </p>

                        <div v-if="withdrawal.status === 'failed'" class="mt-2 flex justify-end gap-2">
                            <button
                                class="rounded-lg bg-accent px-3 py-1 text-xs font-semibold text-on-accent"
                                @click="retry(withdrawal.id)"
                            >
                                Повторить
                            </button>
                        </div>

                        <div v-if="withdrawal.status === 'pending_review'" class="mt-2 flex justify-end gap-2">
                            <button
                                class="rounded-lg bg-accent px-3 py-1 text-xs font-semibold text-on-accent"
                                @click="approve(withdrawal.id)"
                            >
                                Одобрить
                            </button>
                            <button
                                class="rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold text-red-400"
                                @click="startReject(withdrawal.id)"
                            >
                                Отклонить
                            </button>
                        </div>

                        <div v-if="rejectingId === withdrawal.id" class="mt-2 space-y-2">
                            <input v-model="rejectForm.reason" class="input-field text-sm" placeholder="Причина отклонения" />
                            <p v-if="rejectForm.errors.reason" class="text-xs text-red-400">{{ rejectForm.errors.reason }}</p>
                            <div class="flex justify-end gap-2">
                                <button class="rounded-lg bg-red-500/20 px-3 py-1 text-xs font-semibold text-red-400" @click="reject(withdrawal.id)">
                                    Подтвердить
                                </button>
                                <button class="rounded-lg bg-surface-container px-3 py-1 text-xs text-text-dim" @click="rejectingId = null">
                                    Отмена
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="withdrawals.data.length === 0" class="text-center text-text-dim">Нет записей</p>
        </div>
    </AdminLayout>
</template>
