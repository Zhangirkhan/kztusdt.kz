<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminFilters from '@/shared/ui/admin/AdminFilters.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminPagination from '@/shared/ui/admin/AdminPagination.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatUsdt } from '@/utils/formatNumber';
import { Head, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { computed, ref } from 'vue';

const props = defineProps({
    systemWallets: Array,
    wallets: Object,
    deposits: Object,
    filters: Object,
    availableNetworks: { type: Array, default: () => [] },
    meta: Object,
    stats: Object,
});

const { t } = useI18n();

const search = ref(props.filters.q ?? '');

const depositStatusLabels = computed(() => ({
    detected: t('admin.wallets.deposits.status.detected'),
    confirmed: t('admin.wallets.deposits.status.confirmed'),
    credited: t('admin.wallets.deposits.status.credited'),
    failed: t('admin.wallets.deposits.status.failed'),
}));

const systemWalletLabels = computed(() => ({
    hot: t('admin.wallets.systemWallets.hot'),
    gas: t('admin.wallets.systemWallets.gas'),
}));

const statItems = computed(() => [
    { label: t('admin.wallets.stats.clientAddresses'), value: props.stats.wallets_total, color: '#1677ff' },
    { label: t('admin.wallets.stats.depositsTotal'), value: props.stats.deposits_total },
    { label: t('admin.wallets.stats.deposited'), value: props.stats.deposits_credited, color: '#52c41a' },
]);

const networkOptions = computed(() => props.availableNetworks.map((net) => ({
    label: net.code,
    value: net.code,
})));

const depositStatusOptions = computed(() => [
    { label: t('admin.wallets.deposits.filters.all'), value: 'all' },
    { label: t('admin.wallets.deposits.filters.detected'), value: 'detected' },
    { label: t('admin.wallets.deposits.filters.confirmed'), value: 'confirmed' },
    { label: t('admin.wallets.deposits.filters.credited'), value: 'credited' },
    { label: t('admin.wallets.deposits.filters.failed'), value: 'failed' },
]);

const walletColumns = computed(() => [
    { title: t('admin.wallets.clientAddresses.columns.client'), key: 'client' },
    { title: t('admin.wallets.clientAddresses.columns.address'), key: 'address' },
    { title: t('admin.wallets.clientAddresses.columns.balance'), key: 'balance', width: 140 },
]);

const depositColumns = computed(() => [
    { title: t('admin.wallets.deposits.columns.deposit'), key: 'deposit' },
    { title: t('admin.wallets.deposits.columns.amount'), key: 'amount', width: 160 },
    { title: t('admin.wallets.deposits.columns.status'), key: 'status', width: 140 },
    { title: t('admin.wallets.deposits.columns.time'), key: 'time', width: 170 },
]);

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
    return value ? `${value.slice(0, 10)}…${value.slice(-8)}` : t('admin.shared.empty');
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : t('admin.shared.empty');
}

function addressUrl(address) {
    return address && props.meta.explorer_address ? `${props.meta.explorer_address}${address}` : '#';
}

function txUrl(hash) {
    return hash && props.meta.explorer_tx ? `${props.meta.explorer_tx}${hash}` : '#';
}
</script>

<template>
    <Head :title="t('admin.wallets.headTitle')" />

    <AdminLayout>
        <template #title>{{ t('admin.wallets.title', { asset: meta.asset, network: meta.network }) }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <AdminFilters
                v-if="availableNetworks.length > 1"
                :model-value="meta.network"
                :options="networkOptions"
                @change="setNetwork"
            />

            <a-input-search
                v-model:value="search"
                :placeholder="t('admin.wallets.searchPlaceholder')"
                :enter-button="t('admin.shared.actions.find')"
                size="large"
                class="admin-ant-block"
                @search="applyFilters()"
            >
                <template v-if="filters.q" #addonAfter>
                    <a-button @click="search = ''; applyFilters({ q: undefined })">{{ t('admin.shared.actions.reset') }}</a-button>
                </template>
            </a-input-search>

            <a-card :title="t('admin.wallets.systemWallets.cardTitle')" size="small" class="admin-ant-card">
                <a-space wrap class="admin-ant-block">
                    <a-tag>{{ t('admin.wallets.systemWallets.sweep', { status: meta.sweep_enabled ? t('admin.shared.boolean.enabledSingular') : t('admin.shared.boolean.disabledSingular') }) }}</a-tag>
                    <a-tag>{{ t('admin.wallets.systemWallets.withdrawals', { status: meta.withdrawals_enabled ? t('admin.shared.boolean.enabled') : t('admin.shared.boolean.disabled') }) }}</a-tag>
                    <a-tag>{{ t('admin.wallets.systemWallets.confirmations', { count: meta.confirmations_required }) }}</a-tag>
                </a-space>

                <a-row :gutter="[16, 16]">
                    <a-col v-for="wallet in systemWallets" :key="wallet.role" :xs="24" :lg="12">
                        <a-card size="small" :title="systemWalletLabels[wallet.role] ?? wallet.label">
                            <a-typography-text code class="admin-ant-meta">{{ wallet.path }}</a-typography-text>

                            <template v-if="wallet.address">
                                <a :href="addressUrl(wallet.address)" target="_blank" rel="noopener">
                                    <a-typography-text code copyable class="admin-ant-block">{{ wallet.address }}</a-typography-text>
                                </a>
                                <a-row :gutter="12">
                                    <a-col :span="12">
                                        <a-statistic
                                            :title="wallet.native_asset"
                                            :value="wallet.native != null ? formatUsdt(wallet.native, 6) : t('admin.shared.empty')"
                                        />
                                    </a-col>
                                    <a-col :span="12">
                                        <a-statistic
                                            title="USDT"
                                            :value="wallet.usdt != null ? formatUsdt(wallet.usdt, 6) : t('admin.shared.empty')"
                                            :value-style="{ color: '#52c41a' }"
                                        />
                                    </a-col>
                                </a-row>
                            </template>

                            <a-alert v-if="wallet.error" type="error" :message="wallet.error" show-icon style="margin-top: 12px" />
                        </a-card>
                    </a-col>
                </a-row>
            </a-card>

            <a-card :title="t('admin.wallets.clientAddresses.cardTitle')" size="small" class="admin-ant-card">
                <a-table
                    :columns="walletColumns"
                    :data-source="wallets.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'client'">
                            <div>
                                <a-typography-text strong>{{ record.user?.phone ?? t('admin.shared.empty') }}</a-typography-text>
                                <div class="admin-ant-meta">
                                    {{ record.user?.name ?? t('admin.shared.empty') }} · {{ t('admin.wallets.clientAddresses.kyc', { status: record.user?.kyc_status ?? t('admin.shared.empty') }) }}
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'address'">
                            <a :href="addressUrl(record.address)" target="_blank" rel="noopener">
                                <a-typography-text code>{{ record.address }}</a-typography-text>
                            </a>
                        </template>

                        <template v-else-if="column.key === 'balance'">
                            <a-typography-text strong>{{ formatUsdt(record.balance.available, 4) }} {{ record.asset }}</a-typography-text>
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.wallets.clientAddresses.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="wallets" page-param="wallets_page" />
            </a-card>

            <a-card :title="t('admin.wallets.deposits.cardTitle')" size="small" class="admin-ant-card">
                <AdminFilters
                    :model-value="filters.deposit_status"
                    :options="depositStatusOptions"
                    size="small"
                    @change="setDepositStatus"
                />

                <a-table
                    :columns="depositColumns"
                    :data-source="deposits.data"
                    :pagination="false"
                    row-key="id"
                    size="middle"
                >
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'deposit'">
                            <div>
                                <a-typography-text strong>{{ record.user?.phone ?? '—' }}</a-typography-text>
                                <div>
                                    <a :href="txUrl(record.tx_hash)" target="_blank" rel="noopener">
                                        <a-button type="link" size="small" style="padding-left: 0">
                                            tx: {{ short(record.tx_hash) }}
                                        </a-button>
                                    </a>
                                </div>
                            </div>
                        </template>

                        <template v-else-if="column.key === 'amount'">
                            <a-typography-text strong type="success">
                                +{{ formatUsdt(record.amount, 8) }} {{ record.asset }}
                            </a-typography-text>
                        </template>

                        <template v-else-if="column.key === 'status'">
                            <a-tag :color="statusTagColor(record.status)">
                                {{ depositStatusLabels[record.status] ?? record.status }}
                            </a-tag>
                        </template>

                        <template v-else-if="column.key === 'time'">
                            {{ formatDate(record.created_at) }}
                        </template>
                    </template>

                    <template #emptyText>
                        <a-empty :description="t('admin.wallets.deposits.empty')" />
                    </template>
                </a-table>

                <AdminPagination :pagination="deposits" page-param="deposits_page" />
            </a-card>
        </AdminPage>
    </AdminLayout>
</template>
