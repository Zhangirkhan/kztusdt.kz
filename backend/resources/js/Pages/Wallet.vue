<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import AppIcon from '@/shared/ui/icon/AppIcon.vue';
import WalletWithdrawPanel from '@/widgets/wallet-withdraw-panel/ui/WalletWithdrawPanel.vue';
import { localizedPath } from '@/utils/localizedPath';
import { formatUsdt } from '@/utils/formatNumber';
import { usePullToRefresh } from '@/composables/usePullToRefresh';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    balance: Object,
    asset: String,
    networks: { type: Array, default: () => [] },
    selectedNetwork: String,
    deposits: { type: Array, default: () => [] },
    withdraw: {
        type: Object,
        default: () => ({
            feePercent: 0,
            networkFee: '0',
            minAmount: 0,
            autoLimit: 0,
            withdrawalsEnabled: true,
            withdrawals: [],
        }),
    },
    initialTab: {
        type: String,
        default: 'deposit',
    },
});

const WALLET_PROPS = ['balance', 'asset', 'networks', 'selectedNetwork', 'deposits', 'withdraw', 'initialTab'];

const activeTab = ref(props.initialTab === 'withdraw' ? 'withdraw' : 'deposit');
const activeNetwork = ref(props.selectedNetwork || props.networks[0]?.code);
const copied = ref(false);
const lastUpdated = ref(new Date());

const current = computed(
    () => props.networks.find((n) => n.code === activeNetwork.value) || props.networks[0] || {},
);

const currentDeposits = computed(() => props.deposits.filter((d) => d.network === activeNetwork.value));

let autoRefreshTimer = null;

function selectNetwork(code) {
    activeNetwork.value = code;
}

function setTab(tab) {
    activeTab.value = tab;
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

    <ExchangeLayout :show-brand="false">
        <template #title>Кошелёк</template>

        <template #header-actions>
            <Link
                :href="localizedPath('/wallet/history')"
                class="btn-icon wallet-header-btn flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-accent"
                aria-label="История"
            >
                <AppIcon name="history" :size="20" :stroke-width="2" />
            </Link>
        </template>

        <div
            class="wallet-page wallet-refresh"
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

            <section class="wallet-balance-card">
                <p class="wallet-balance-card__label">Общий баланс</p>
                <div class="wallet-balance-card__row">
                    <span class="wallet-balance-card__amount">{{ balance.usdt }}</span>
                    <span class="wallet-balance-card__asset">{{ asset }}</span>
                </div>
                <p class="wallet-balance-card__hint">Единый баланс для всех сетей</p>
                <p class="wallet-balance-card__updated">
                    Обновлено {{ formatUpdatedAt(lastUpdated) }}
                </p>
            </section>

            <div class="wallet-action-bar" role="tablist" aria-label="Действия кошелька">
                <button
                    type="button"
                    role="tab"
                    class="wallet-action-bar__btn"
                    :class="activeTab === 'deposit' ? 'wallet-action-bar__btn--primary' : 'wallet-action-bar__btn--secondary'"
                    :aria-selected="activeTab === 'deposit'"
                    @click="setTab('deposit')"
                >
                    Пополнить
                </button>
                <button
                    type="button"
                    role="tab"
                    class="wallet-action-bar__btn"
                    :class="activeTab === 'withdraw' ? 'wallet-action-bar__btn--primary' : 'wallet-action-bar__btn--secondary'"
                    :aria-selected="activeTab === 'withdraw'"
                    @click="setTab('withdraw')"
                >
                    Вывести
                </button>
            </div>

            <template v-if="activeTab === 'deposit'">
                <section class="wallet-section">
                    <div class="wallet-section-head">
                        <p class="wallet-section-head__label">Выберите сеть</p>
                        <span class="material-symbols-outlined wallet-section-head__icon" aria-hidden="true">info</span>
                    </div>

                    <div v-if="networks.length > 1" class="wallet-network-grid">
                        <button
                            v-for="net in networks"
                            :key="net.code"
                            type="button"
                            class="wallet-network-pill"
                            :class="net.code === activeNetwork ? 'wallet-network-pill--active' : 'wallet-network-pill--inactive'"
                            @click="selectNetwork(net.code)"
                        >
                            {{ net.code }}
                        </button>
                    </div>

                    <div v-if="current.pending" class="wallet-pending">
                        <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
                        Кошелёк создаётся, потяните вниз для обновления
                    </div>

                    <template v-else>
                        <label class="wallet-address-label">
                            Ваш адрес {{ current.asset }} · {{ current.label }}
                        </label>
                        <div class="wallet-address-field">
                            <p class="wallet-address-field__text">{{ current.address }}</p>
                            <button
                                type="button"
                                class="wallet-address-field__copy"
                                :aria-label="copied ? 'Скопировано' : 'Копировать адрес'"
                                @click="copyAddress"
                            >
                                <AppIcon :name="copied ? 'check' : 'copy'" :size="20" :stroke-width="2" />
                            </button>
                        </div>
                        <button
                            type="button"
                            class="btn-primary wallet-copy-cta"
                            @click="copyAddress"
                        >
                            <AppIcon :name="copied ? 'check' : 'copy'" :size="20" :stroke-width="2" />
                            {{ copied ? 'Адрес скопирован' : 'Скопировать адрес' }}
                        </button>
                        <div class="wallet-warning">
                            <span class="material-symbols-outlined wallet-warning__icon" aria-hidden="true">warning</span>
                            <p class="wallet-warning__text">
                                Отправляйте только <strong>{{ current.asset }}</strong> через сеть
                                <strong>{{ current.code }}</strong> на этот адрес. Другие активы могут быть потеряны безвозвратно.
                            </p>
                        </div>
                    </template>
                </section>

                <section v-if="currentDeposits.length" class="wallet-section wallet-section--card">
                    <p class="wallet-section-head__label mb-3">История депозитов · {{ current.code }}</p>
                    <div class="wallet-deposits">
                        <div
                            v-for="d in currentDeposits"
                            :key="d.id"
                            class="wallet-deposit-item"
                        >
                            <div class="wallet-deposit-item__icon">
                                <span class="material-symbols-outlined text-lg">south_west</span>
                            </div>
                            <div class="wallet-deposit-item__info">
                                <p class="wallet-deposit-item__amount">+{{ formatUsdt(d.amount, 8) }} {{ asset }}</p>
                                <a
                                    v-if="d.explorer_tx"
                                    :href="`${d.explorer_tx}${d.tx_hash}`"
                                    target="_blank"
                                    rel="noopener"
                                    class="wallet-deposit-item__hash"
                                >
                                    {{ short(d.tx_hash) }}
                                </a>
                                <span v-else class="wallet-deposit-item__hash">{{ short(d.tx_hash) }}</span>
                            </div>
                            <div class="wallet-deposit-item__status">
                                <span
                                    class="wallet-deposit-item__badge"
                                    :class="d.status === 'credited' ? 'wallet-deposit-item__badge--success' : ''"
                                >
                                    {{ statusLabel[d.status] ?? d.status }}
                                </span>
                                <p v-if="d.status !== 'credited'" class="wallet-deposit-item__confirm">
                                    {{ d.confirmations }}/{{ current.confirmationsRequired }}
                                </p>
                            </div>
                        </div>
                    </div>
                </section>
            </template>

            <WalletWithdrawPanel
                v-else
                :balance="balance"
                :networks="networks"
                :withdraw="withdraw"
            />

            <p class="wallet-footnote">
                Баланс обновляется каждую минуту · потяните экран вниз для ручного обновления
            </p>
        </div>
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
    margin: -0.75rem 0 0.25rem;
}
</style>
