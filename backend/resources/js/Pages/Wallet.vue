<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import AppIcon from '@/shared/ui/icon/AppIcon.vue';
import { useDepositStatusLabels } from '@/shared/lib/i18n/useOrderStatusLabels';
import WalletWithdrawPanel from '@/widgets/wallet-withdraw-panel/ui/WalletWithdrawPanel.vue';
import { localizedPath } from '@/utils/localizedPath';
import { formatUsdt } from '@/utils/formatNumber';
import { usePullToRefresh } from '@/composables/usePullToRefresh';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

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
const { t, locale } = useI18n();
const depositStatusLabels = useDepositStatusLabels();

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
    const localeMap = {
        ru: 'ru-RU',
        en: 'en-US',
        kk: 'kk-KZ',
    };

    return date.toLocaleTimeString(localeMap[locale.value] ?? locale.value, { hour: '2-digit', minute: '2-digit', second: '2-digit' });
}

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
    <Head :title="t('wallet.title')" />

    <ExchangeLayout :show-brand="false">
        <template #title>{{ t('wallet.title') }}</template>

        <template #header-actions>
            <Link
                :href="localizedPath('/wallet/history')"
                class="btn-icon wallet-header-btn flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-accent"
                :aria-label="t('exchange.historyAria')"
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
                    {{ isRefreshing ? t('wallet.pullUpdating') : pullDistance >= 72 ? t('wallet.pullRelease') : t('wallet.pullRefresh') }}
                </span>
            </div>

            <section class="wallet-balance-card">
                <p class="wallet-balance-card__label">{{ t('wallet.totalBalance') }}</p>
                <div class="wallet-balance-card__row">
                    <span class="wallet-balance-card__amount">{{ balance.usdt }}</span>
                    <span class="wallet-balance-card__asset">{{ asset }}</span>
                </div>
                <p class="wallet-balance-card__hint">{{ t('wallet.unifiedBalanceHint') }}</p>
                <p class="wallet-balance-card__updated">
                    {{ t('wallet.updatedAt', { time: formatUpdatedAt(lastUpdated) }) }}
                </p>
            </section>

            <div class="wallet-action-bar" role="tablist" :aria-label="t('wallet.actionBarAria')">
                <button
                    type="button"
                    role="tab"
                    class="wallet-action-bar__btn"
                    :class="activeTab === 'deposit' ? 'wallet-action-bar__btn--primary' : 'wallet-action-bar__btn--secondary'"
                    :aria-selected="activeTab === 'deposit'"
                    @click="setTab('deposit')"
                >
                    {{ t('wallet.depositTab') }}
                </button>
                <button
                    type="button"
                    role="tab"
                    class="wallet-action-bar__btn"
                    :class="activeTab === 'withdraw' ? 'wallet-action-bar__btn--primary' : 'wallet-action-bar__btn--secondary'"
                    :aria-selected="activeTab === 'withdraw'"
                    @click="setTab('withdraw')"
                >
                    {{ t('wallet.withdrawTab') }}
                </button>
            </div>

            <template v-if="activeTab === 'deposit'">
                <section class="wallet-section">
                    <div class="wallet-section-head">
                        <p class="wallet-section-head__label">{{ t('wallet.selectNetwork') }}</p>
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
                        {{ t('wallet.walletCreating') }}
                    </div>

                    <template v-else>
                        <label class="wallet-address-label">
                            {{ t('wallet.yourAddress', { asset: current.asset, label: current.label }) }}
                        </label>
                        <div class="wallet-address-field">
                            <p class="wallet-address-field__text">{{ current.address }}</p>
                            <button
                                type="button"
                                class="wallet-address-field__copy"
                                :aria-label="copied ? t('wallet.copied') : t('wallet.copyAddressAria')"
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
                            {{ copied ? t('wallet.addressCopied') : t('wallet.copyAddress') }}
                        </button>
                        <div class="wallet-warning">
                            <span class="material-symbols-outlined wallet-warning__icon" aria-hidden="true">warning</span>
                            <p class="wallet-warning__text">
                                {{ t('wallet.depositWarning', { asset: current.asset, code: current.code }) }}
                            </p>
                        </div>
                    </template>
                </section>

                <section v-if="currentDeposits.length" class="wallet-section wallet-section--card">
                    <p class="wallet-section-head__label mb-3">{{ t('wallet.depositHistory', { code: current.code }) }}</p>
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
                                    {{ depositStatusLabels[d.status] ?? d.status }}
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
                {{ t('wallet.footerHint') }}
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
