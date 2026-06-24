<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatKzt, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, router } from '@inertiajs/vue3';

const props = defineProps({
    orders: Object,
    filterStatus: String,
    filterDirection: String,
    stats: Object,
});

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ждёт оплату KZT',
    payment_proof_uploaded: 'Скрин загружен',
    pending_admin_confirmation: 'Ждёт подтверждения',
    kzt_received: 'KZT получены',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    dispute: 'Спор',
    manual_review: 'Ручная проверка',
};

const statusColors = {
    completed: 'text-accent',
    cancelled: 'text-text-dim',
    failed: 'text-red-400',
    dispute: 'text-red-400',
};

function setFilter(status) {
    router.get('/admin/orders', { status, direction: props.filterDirection }, { preserveState: true });
}

function setDirection(direction) {
    router.get('/admin/orders', { status: props.filterStatus, direction }, { preserveState: true });
}

function formatDate(value) {
    return new Date(value).toLocaleString('ru-RU');
}
</script>

<template>
    <Head title="Заявки обмена" />

    <AdminLayout>
        <template #title>Заявки обмена KZT/USDT</template>

        <div class="mb-6 grid grid-cols-3 gap-3">
            <div class="card">
                <p class="text-body-sm text-text-dim">Ожидают действия</p>
                <p class="text-headline-md text-amber-400">{{ stats.pending }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Выполнено</p>
                <p class="text-headline-md text-accent">{{ stats.completed }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Отменено / ошибки</p>
                <p class="text-headline-md text-red-400">{{ stats.cancelled }}</p>
            </div>
        </div>

        <div class="mb-3 flex flex-wrap gap-2">
            <button
                v-for="item in [
                    { key: 'active', label: 'Активные' },
                    { key: 'pending_admin_confirmation', label: 'Ждут подтверждения' },
                    { key: 'completed', label: 'Выполненные' },
                    { key: 'cancelled', label: 'Отменённые' },
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

        <div class="mb-6 flex gap-2">
            <button
                v-for="item in [
                    { key: 'all', label: 'Покупка + продажа' },
                    { key: 'buy', label: 'Покупка' },
                    { key: 'sell', label: 'Продажа' },
                ]"
                :key="item.key"
                class="rounded-xl px-3 py-1 text-xs font-semibold transition"
                :class="filterDirection === item.key ? 'bg-accent/20 text-accent' : 'bg-surface-container text-text-dim'"
                @click="setDirection(item.key)"
            >
                {{ item.label }}
            </button>
        </div>

        <div class="space-y-3">
            <Link
                v-for="order in orders.data"
                :key="order.id"
                :href="`/admin/orders/${order.id}`"
                class="card block"
            >
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <p class="font-semibold">
                            №{{ order.id }} ·
                            <span :class="order.direction === 'buy' ? 'text-accent' : 'text-sky-400'">
                                {{ order.direction === 'buy' ? 'Покупка' : 'Продажа' }}
                            </span>
                            · {{ formatKzt(order.fiat_amount) }} ₸ ↔
                            {{ formatUsdt(order.crypto_amount, 2) }} USDT
                        </p>
                        <p class="mt-1 text-body-sm text-text-muted">
                            {{ order.user?.name ?? '—' }} · {{ order.user?.phone ?? '—' }} · {{ formatDate(order.created_at) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <span class="text-xs font-semibold uppercase" :class="statusColors[order.status] ?? 'text-amber-400'">
                            {{ statusLabels[order.status] ?? order.status }}
                        </span>
                        <p v-if="order.fiat_payment_request?.proof_file_path" class="mt-1 text-xs text-accent">📎 скрин загружен</p>
                    </div>
                </div>
            </Link>

            <p v-if="orders.data.length === 0" class="text-center text-text-dim">Нет заявок</p>
        </div>

        <div v-if="orders.links" class="mt-6 flex flex-wrap justify-center gap-1">
            <Link
                v-for="(link, i) in orders.links"
                :key="i"
                :href="link.url ?? '#'"
                class="rounded-lg px-3 py-1 text-sm"
                :class="link.active ? 'bg-accent text-on-accent' : link.url ? 'bg-surface-container text-text-dim' : 'text-text-dim/40'"
                v-html="link.label"
            />
        </div>
    </AdminLayout>
</template>
