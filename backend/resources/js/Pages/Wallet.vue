<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { formatUsdt } from '@/utils/formatNumber';
import { usePullToRefresh } from '@/composables/usePullToRefresh';
import { useTapFeedback } from '@/composables/useTapFeedback';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    balance: Object,
    asset: String,
    networks: { type: Array, default: () => [] },
    selectedNetwork: String,
    deposits: { type: Array, default: () => [] },
});

const WALLET_PROPS = ['balance', 'asset', 'networks', 'selectedNetwork', 'deposits'];

const activeNetwork = ref(props.selectedNetwork || props.networks[0]?.code);

const current = computed(
    () => props.networks.find((n) => n.code === activeNetwork.value) || props.networks[0] || {},
);

const currentDeposits = computed(() => props.deposits.filter((d) => d.network === activeNetwork.value));

const copied = ref(false);
const showDeposit = ref(false);
const lastUpdated = ref(new Date());
const { tapping: depositTapping, wrap: wrapTap } = useTapFeedback();

let autoRefreshTimer = null;

function selectNetwork(code) {
    activeNetwork.value = code;
}

function refreshWallet() {
    return new Promise((resolve) => {
        router.reload({
            only: WALLET_PROPS,
            preserveScroll: true,
            onFinish: () => {
                lastUpdated.value = new Date();
                resolve();
            },
        });
    });
}

const { pullDistance, isRefreshing } = usePullToRefresh(refreshWallet);

async function copyAddress() {
    if (!current.value.address) {
        return;
    }

    try {
        await navigator.clipboard.writeText(current.value.address);
        copied.value = true;
        setTimeout(() => (copied.value = false), 1500);
    } catch {
        // clipboard may be unavailable on insecure origins
    }
}

async function openDeposit() {
    showDeposit.value = true;

    if (current.value.address) {
        await nextTick();
        await copyAddress();
    }
}

const handleOpenDeposit = wrapTap(openDeposit);
const handleCopyAddress = wrapTap(copyAddress);

function closeDeposit() {
    showDeposit.value = false;
}

function formatUpdatedAt(date) {
    return date.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

const statusLabel = {
    detected: 'Обнаружен',
    confirmed: 'Подтверждается',
    credited: 'Зачислен',
    failed: 'Ошибка',
};

function short(hash) {
    return hash ? `${hash.slice(0, 8)}…${hash.slice(-6)}` : '';
}

onMounted(() => {
    autoRefreshTimer = window.setInterval(() => {
        if (document.visibilityState !== 'visible' || isRefreshing.value) {
            return;
        }

        refreshWallet();
    }, 60_000);
});

onUnmounted(() => {
    if (autoRefreshTimer !== null) {
        window.clearInterval(autoRefreshTimer);
    }
});
</script>

<template>
    <Head title="Кошелёк" />

    <ExchangeLayout>
        <template #title>Кошелёк</template>

        <div
            class="wallet-refresh"
            :class="{ 'wallet-refresh--active': pullDistance > 0 || isRefreshing }"
            :style="{ transform: pullDistance > 0 ? `translateY(${pullDistance}px)` : undefined }"
        >
            <div
                class="wallet-refresh-indicator"
                :style="{ height: `${Math.max(pullDistance, isRefreshing ? 48 : 0)}px` }"
            >
                <span
                    class="material-symbols-outlined text-accent"
                    :class="{ 'animate-spin': isRefreshing }"
                >
                    {{ isRefreshing ? 'progress_activity' : 'arrow_downward' }}
                </span>
                <span class="text-xs text-text-dim">
                    {{ isRefreshing ? 'Обновление…' : pullDistance >= 72 ? 'Отпустите' : 'Потяните вниз' }}
                </span>
            </div>

            <section class="card mb-stack-element bg-gradient-to-br from-accent/20 to-surface-container">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-label-caps uppercase text-text-dim">Баланс {{ asset }}</p>
                        <p class="mt-2 text-4xl font-bold">{{ balance.usdt }}</p>
                        <p class="mt-1 text-body-sm text-text-muted">Единый баланс для всех сетей</p>
                    </div>
                    <p class="text-right text-[10px] leading-tight text-text-dim">
                        Обновлено<br>{{ formatUpdatedAt(lastUpdated) }}
                    </p>
                </div>
            </section>

            <section class="grid grid-cols-2 gap-3">
                <button
                    type="button"
                    class="btn-primary"
                    :class="{ 'animate-press': depositTapping }"
                    @click="handleOpenDeposit"
                >
                    Пополнить
                </button>
                <Link href="/withdraw" class="btn-secondary text-center">Вывести</Link>
            </section>

            <section class="mt-stack-section card">
                <p class="text-label-caps uppercase text-text-dim">Сеть пополнения</p>

                <div v-if="networks.length > 1" class="mt-3 grid grid-cols-2 gap-2">
                    <button
                        v-for="net in networks"
                        :key="net.code"
                        type="button"
                        class="rounded-xl border px-3 py-2 text-sm font-semibold transition-colors"
                        :class="net.code === activeNetwork
                            ? 'border-accent bg-accent/15 text-accent'
                            : 'border-outline-variant/40 bg-surface-container-low text-text-muted'"
                        @click="selectNetwork(net.code)"
                    >
                        {{ net.code }}
                    </button>
                </div>

                <p class="mt-3 text-body-sm text-text-muted">{{ current.label }}</p>

                <div v-if="current.pending" class="mt-3 flex items-center gap-2 text-body-sm text-text-muted">
                    <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
                    Кошелёк создаётся, потяните вниз для обновления
                </div>

                <template v-else>
                    <p class="mt-3 break-all font-mono text-body-sm text-on-surface">
                        {{ current.address }}
                    </p>
                    <button type="button" class="btn-secondary mt-3 flex items-center justify-center gap-2" @click="handleCopyAddress">
                        <span class="material-symbols-outlined text-base">{{ copied ? 'check' : 'content_copy' }}</span>
                        {{ copied ? 'Скопировано' : 'Копировать адрес' }}
                    </button>
                    <p class="mt-3 text-body-sm text-text-dim">
                        Отправляйте только {{ current.asset }} в сети {{ current.code }}. Другие активы будут потеряны.
                    </p>
                </template>
            </section>

            <section v-if="currentDeposits.length" class="mt-stack-element card">
                <p class="mb-3 text-label-caps uppercase text-text-dim">История депозитов · {{ current.code }}</p>
                <div class="space-y-2">
                    <div
                        v-for="d in currentDeposits"
                        :key="d.id"
                        class="flex items-center justify-between rounded-xl bg-surface-container-low px-4 py-3"
                    >
                        <div>
                            <p class="font-semibold text-on-surface">+{{ formatUsdt(d.amount, 8) }} {{ asset }}</p>
                            <a
                                v-if="d.explorer_tx"
                                :href="`${d.explorer_tx}${d.tx_hash}`"
                                target="_blank"
                                rel="noopener"
                                class="font-mono text-xs text-text-dim hover:text-accent"
                            >
                                {{ short(d.tx_hash) }}
                            </a>
                            <span v-else class="font-mono text-xs text-text-dim">{{ short(d.tx_hash) }}</span>
                        </div>
                        <div class="text-right">
                            <span
                                class="text-xs font-semibold"
                                :class="d.status === 'credited' ? 'text-accent' : 'text-text-dim'"
                            >
                                {{ statusLabel[d.status] ?? d.status }}
                            </span>
                            <p v-if="d.status !== 'credited'" class="text-xs text-text-dim">
                                {{ d.confirmations }}/{{ current.confirmationsRequired }}
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <p class="mt-4 text-center text-xs text-text-dim">
                Баланс обновляется каждую минуту · потяните экран вниз для ручного обновления
            </p>
        </div>

        <Teleport to="body">
            <div v-if="showDeposit" class="sheet-backdrop" @click.self="closeDeposit">
                <div class="sheet-panel">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <p class="text-label-caps uppercase text-text-dim">Пополнение</p>
                            <h2 class="mt-1 text-headline-md">{{ current.asset }} · {{ current.code }}</h2>
                        </div>
                        <button
                            type="button"
                            class="btn-icon flex h-10 w-10 rounded-full bg-surface-container-high text-text-dim"
                            aria-label="Закрыть"
                            @click="closeDeposit"
                        >
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div v-if="networks.length > 1" class="mb-4 grid grid-cols-2 gap-2">
                        <button
                            v-for="net in networks"
                            :key="net.code"
                            type="button"
                            class="rounded-xl border px-3 py-2 text-sm font-semibold transition-colors"
                            :class="net.code === activeNetwork
                                ? 'border-accent bg-accent/15 text-accent'
                                : 'border-outline-variant/40 bg-surface-container-low text-text-muted'"
                            @click="selectNetwork(net.code)"
                        >
                            {{ net.code }}
                        </button>
                    </div>

                    <div v-if="current.pending" class="flex items-center gap-2 text-body-sm text-text-muted">
                        <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
                        Кошелёк ещё создаётся. Подождите минуту и потяните экран вниз.
                    </div>

                    <template v-else>
                        <p class="text-body-sm text-text-muted">
                            Переведите {{ current.asset }} на адрес ниже. Зачисление после {{ current.confirmationsRequired }} подтверждений сети.
                        </p>
                        <p class="mt-4 break-all rounded-xl bg-surface-container-low p-4 font-mono text-body-sm text-on-surface">
                            {{ current.address }}
                        </p>
                        <button type="button" class="btn-primary mt-4 flex items-center justify-center gap-2" @click="handleCopyAddress">
                            <span class="material-symbols-outlined text-base">{{ copied ? 'check' : 'content_copy' }}</span>
                            {{ copied ? 'Адрес скопирован' : 'Скопировать адрес' }}
                        </button>
                        <p class="mt-4 text-xs text-error">
                            Отправляйте только {{ current.asset }} в сети {{ current.code }}. Другие монеты будут потеряны.
                        </p>
                    </template>
                </div>
            </div>
        </Teleport>
    </ExchangeLayout>
</template>

<style scoped>
.wallet-refresh {
    transition: transform 0.2s ease;
}

.wallet-refresh-indicator {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    gap: 0.25rem;
    overflow: hidden;
    margin: -0.5rem 0 0.5rem;
}
</style>
