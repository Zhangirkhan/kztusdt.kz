<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router } from '@inertiajs/vue3';

defineProps({
    sweeps: Object,
    filterStatus: String,
    stats: Object,
    enabled: Boolean,
});

const statusColors = {
    pending: 'text-text-dim',
    waiting_gas: 'text-amber-400',
    gas_sent: 'text-sky-400',
    sweeping: 'text-sky-400',
    swept: 'text-accent',
    failed: 'text-red-400',
    manual_review: 'text-red-400',
};

function setFilter(status) {
    router.get('/admin/sweeps', { status }, { preserveState: true });
}

function retry(id) {
    router.post(`/admin/sweeps/${id}/retry`, {}, { preserveScroll: true });
}

function short(hash) {
    return hash ? `${hash.slice(0, 8)}…${hash.slice(-6)}` : '—';
}
</script>

<template>
    <Head title="Sweeps" />

    <AdminLayout>
        <template #title>Sweep депозитов</template>

        <div
            v-if="!enabled"
            class="mb-6 rounded-xl border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm text-amber-300"
        >
            Sweeper выключен (SWEEP_ENABLED=false). Транзакции не отправляются. Включите после тестов на BSC testnet.
        </div>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="card">
                <p class="text-body-sm text-text-dim">Ждут газ</p>
                <p class="text-headline-md text-amber-400">{{ stats.waiting_gas }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">В процессе</p>
                <p class="text-headline-md text-sky-400">{{ stats.in_progress }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Собрано</p>
                <p class="text-headline-md text-accent">{{ stats.swept }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Требуют внимания</p>
                <p class="text-headline-md text-red-400">{{ stats.attention }}</p>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="item in [
                    { key: 'active', label: 'Активные' },
                    { key: 'swept', label: 'Собранные' },
                    { key: 'attention', label: 'Внимание' },
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
            <div v-for="sweep in sweeps.data" :key="sweep.id" class="card">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold text-on-surface">
                            #{{ sweep.id }} · {{ formatUsdt(sweep.amount, 8) }} {{ sweep.asset }}
                        </p>
                        <p class="mt-1 text-body-sm text-text-muted">
                            {{ sweep.user?.phone ?? '—' }} · {{ short(sweep.from_address) }} → {{ short(sweep.to_address) }}
                        </p>
                        <p v-if="sweep.last_error" class="mt-1 text-xs text-red-400">{{ sweep.last_error }}</p>
                    </div>
                    <div class="flex items-center gap-3 text-right">
                        <div>
                            <span class="text-xs font-semibold uppercase" :class="statusColors[sweep.status]">
                                {{ sweep.status }}
                            </span>
                            <p class="mt-1 text-xs text-text-dim">
                                gas: {{ short(sweep.gas_tx_hash) }} · tx: {{ short(sweep.sweep_tx_hash) }}
                            </p>
                        </div>
                        <button
                            v-if="['manual_review', 'failed'].includes(sweep.status)"
                            class="rounded-lg bg-surface-container-high px-3 py-1 text-xs font-semibold text-accent hover:bg-accent hover:text-on-accent"
                            @click="retry(sweep.id)"
                        >
                            Повторить
                        </button>
                    </div>
                </div>
            </div>

            <p v-if="sweeps.data.length === 0" class="text-center text-text-dim">Нет записей</p>
        </div>
    </AdminLayout>
</template>
