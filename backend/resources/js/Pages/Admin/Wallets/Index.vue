<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    systemWallets: Array,
    wallets: Object,
    deposits: Object,
    filters: Object,
    availableNetworks: { type: Array, default: () => [] },
    meta: Object,
    stats: Object,
});

const search = ref(props.filters.q ?? '');

const depositStatusLabels = {
    detected: 'Обнаружен',
    confirmed: 'Подтверждается',
    credited: 'Зачислен',
    failed: 'Ошибка',
};

const depositStatusColors = {
    detected: 'text-amber-400',
    confirmed: 'text-sky-400',
    credited: 'text-accent',
    failed: 'text-red-400',
};

const systemWalletLabels = {
    hot: 'Hot wallet (выводы и sweep)',
    gas: 'Gas wallet (комиссии сети)',
};

function applyFilters(extra = {}) {
    router.get('/admin/wallets', {
        q: search.value || undefined,
        deposit_status: props.filters.deposit_status,
        network: props.filters.network,
        ...extra,
    }, { preserveState: true, replace: true });
}

function setDepositStatus(status) {
    applyFilters({ deposit_status: status });
}

function setNetwork(network) {
    applyFilters({ network });
}

function short(value) {
    return value ? `${value.slice(0, 10)}…${value.slice(-8)}` : '—';
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}

function addressUrl(address) {
    return address && props.meta.explorer_address ? `${props.meta.explorer_address}${address}` : '#';
}

function txUrl(hash) {
    return hash && props.meta.explorer_tx ? `${props.meta.explorer_tx}${hash}` : '#';
}
</script>

<template>
    <Head title="Кошельки" />

    <AdminLayout>
        <template #title>Кошельки · {{ meta.asset }} · {{ meta.network }}</template>

        <div class="mb-6 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <div class="card">
                <p class="text-body-sm text-text-dim">Клиентских адресов</p>
                <p class="text-headline-md text-accent">{{ stats.wallets_total }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Депозитов всего</p>
                <p class="text-headline-md">{{ stats.deposits_total }}</p>
            </div>
            <div class="card">
                <p class="text-body-sm text-text-dim">Зачислено</p>
                <p class="text-headline-md text-accent">{{ stats.deposits_credited }}</p>
            </div>
        </div>

        <div v-if="availableNetworks.length > 1" class="mb-6 flex flex-wrap gap-2">
            <button
                v-for="net in availableNetworks"
                :key="net.code"
                type="button"
                class="rounded-xl border px-4 py-2 text-sm font-semibold transition-colors"
                :class="net.code === meta.network
                    ? 'border-accent bg-accent/15 text-accent'
                    : 'border-outline-variant/40 bg-surface-container text-text-dim'"
                @click="setNetwork(net.code)"
            >
                {{ net.code }}
            </button>
        </div>

        <form class="mb-8 flex flex-wrap gap-3" @submit.prevent="applyFilters()">
            <input
                v-model="search"
                type="search"
                class="input-field min-w-[240px] flex-1"
                placeholder="Телефон, адрес, tx hash…"
            />
            <button type="submit" class="btn-primary">Найти</button>
            <button
                v-if="filters.q"
                type="button"
                class="btn-secondary"
                @click="search = ''; applyFilters({ q: undefined })"
            >
                Сброс
            </button>
        </form>

        <section class="mb-10">
            <h2 class="mb-4 text-label-caps uppercase text-text-dim">Системные кошельки</h2>

            <div class="mb-4 flex flex-wrap gap-2 text-xs">
                <span class="rounded-lg bg-surface-container px-3 py-1 text-text-dim">
                    Sweep: {{ meta.sweep_enabled ? 'включён' : 'выключен' }}
                </span>
                <span class="rounded-lg bg-surface-container px-3 py-1 text-text-dim">
                    Выводы: {{ meta.withdrawals_enabled ? 'включены' : 'выключены' }}
                </span>
                <span class="rounded-lg bg-surface-container px-3 py-1 text-text-dim">
                    Подтверждений: {{ meta.confirmations_required }}
                </span>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div
                    v-for="wallet in systemWallets"
                    :key="wallet.role"
                    class="card"
                    :class="wallet.role === 'hot' ? 'border border-accent/30' : ''"
                >
                    <p class="text-label-caps uppercase text-text-dim">
                        {{ systemWalletLabels[wallet.role] ?? wallet.label }}
                    </p>
                    <p class="mt-1 font-mono text-xs text-text-muted">{{ wallet.path }}</p>

                    <template v-if="wallet.address">
                        <a
                            :href="addressUrl(wallet.address)"
                            target="_blank"
                            rel="noopener"
                            class="mt-3 block break-all font-mono text-sm text-accent hover:underline"
                        >
                            {{ wallet.address }}
                        </a>
                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm">
                            <div class="rounded-xl bg-surface-container-low px-4 py-3">
                                <p class="text-text-dim">{{ wallet.native_asset }}</p>
                                <p class="mt-1 font-semibold">{{ wallet.native != null ? formatUsdt(wallet.native, 6) : '—' }}</p>
                            </div>
                            <div class="rounded-xl bg-surface-container-low px-4 py-3">
                                <p class="text-text-dim">USDT</p>
                                <p class="mt-1 font-semibold text-accent">{{ wallet.usdt != null ? formatUsdt(wallet.usdt, 6) : '—' }}</p>
                            </div>
                        </div>
                    </template>

                    <p v-if="wallet.error" class="mt-3 text-sm text-red-400">{{ wallet.error }}</p>
                </div>
            </div>
        </section>

        <section class="mb-10">
            <h2 class="mb-4 text-label-caps uppercase text-text-dim">Клиентские адреса депозита</h2>

            <div class="space-y-3">
                <div v-for="wallet in wallets.data" :key="wallet.id" class="card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="font-semibold text-on-surface">
                                {{ wallet.user?.phone ?? '—' }}
                                <span class="text-text-dim">· {{ wallet.user?.name ?? '—' }}</span>
                            </p>
                            <p class="mt-1 text-xs text-text-dim">
                                KYC: {{ wallet.user?.kyc_status ?? '—' }}
                                · путь {{ wallet.derivation_path ?? '—' }}
                            </p>
                            <a
                                :href="addressUrl(wallet.address)"
                                target="_blank"
                                rel="noopener"
                                class="mt-2 block break-all font-mono text-sm text-accent hover:underline"
                            >
                                {{ wallet.address }}
                            </a>
                        </div>
                        <div class="text-right text-sm">
                            <p class="text-text-dim">Баланс в системе</p>
                            <p class="font-semibold text-accent">
                                {{ formatUsdt(wallet.balance.available, 4) }} {{ wallet.asset }}
                            </p>
                            <p v-if="parseFloat(wallet.balance.locked) > 0" class="mt-1 text-xs text-amber-400">
                                заблокировано {{ formatUsdt(wallet.balance.locked, 4) }}
                            </p>
                            <p class="mt-2 text-xs text-text-dim">{{ formatDate(wallet.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <p v-if="wallets.data.length === 0" class="text-center text-text-dim">Адресов не найдено</p>
            </div>

            <div v-if="wallets.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="link in wallets.links"
                    :key="link.label"
                    type="button"
                    class="rounded-lg px-3 py-1 text-sm"
                    :class="link.active ? 'bg-accent text-on-accent' : 'bg-surface-container text-text-dim'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url)"
                    v-html="link.label"
                />
            </div>
        </section>

        <section>
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-label-caps uppercase text-text-dim">История депозитов</h2>
                <div class="flex flex-wrap gap-2">
                    <button
                        v-for="item in [
                            { key: 'all', label: 'Все' },
                            { key: 'detected', label: 'Обнаружены' },
                            { key: 'confirmed', label: 'Подтверждаются' },
                            { key: 'credited', label: 'Зачислены' },
                            { key: 'failed', label: 'Ошибки' },
                        ]"
                        :key="item.key"
                        type="button"
                        class="rounded-xl px-3 py-1.5 text-xs font-semibold transition"
                        :class="filters.deposit_status === item.key ? 'bg-accent text-on-accent' : 'bg-surface-container text-text-dim'"
                        @click="setDepositStatus(item.key)"
                    >
                        {{ item.label }}
                    </button>
                </div>
            </div>

            <div class="space-y-3">
                <div v-for="deposit in deposits.data" :key="deposit.id" class="card">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold text-on-surface">
                                +{{ formatUsdt(deposit.amount, 8) }} {{ deposit.asset }}
                                <span class="text-text-dim">· {{ deposit.user?.phone ?? '—' }}</span>
                            </p>
                            <p class="mt-1 text-xs text-text-dim">
                                {{ short(deposit.from_address) }} → {{ short(deposit.to_address) }}
                            </p>
                            <a
                                :href="txUrl(deposit.tx_hash)"
                                target="_blank"
                                rel="noopener"
                                class="mt-1 block font-mono text-xs text-accent hover:underline"
                            >
                                tx: {{ short(deposit.tx_hash) }}
                            </a>
                        </div>
                        <div class="text-right">
                            <span
                                class="text-xs font-semibold uppercase"
                                :class="depositStatusColors[deposit.status] ?? 'text-text-dim'"
                            >
                                {{ depositStatusLabels[deposit.status] ?? deposit.status }}
                            </span>
                            <p v-if="deposit.status !== 'credited'" class="mt-1 text-xs text-text-dim">
                                {{ deposit.confirmations }}/{{ meta.confirmations_required }} conf.
                            </p>
                            <p class="mt-2 text-xs text-text-dim">{{ formatDate(deposit.created_at) }}</p>
                        </div>
                    </div>
                </div>

                <p v-if="deposits.data.length === 0" class="text-center text-text-dim">Депозитов не найдено</p>
            </div>

            <div v-if="deposits.links?.length > 3" class="mt-4 flex flex-wrap gap-2">
                <button
                    v-for="link in deposits.links"
                    :key="link.label"
                    type="button"
                    class="rounded-lg px-3 py-1 text-sm"
                    :class="link.active ? 'bg-accent text-on-accent' : 'bg-surface-container text-text-dim'"
                    :disabled="!link.url"
                    @click="link.url && router.get(link.url)"
                    v-html="link.label"
                />
            </div>
        </section>
    </AdminLayout>
</template>
