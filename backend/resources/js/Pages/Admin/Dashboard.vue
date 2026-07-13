<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { Head, Link } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';
import { useI18n } from 'vue-i18n';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    services: {
        type: Object,
        default: () => ({}),
    },
});

const { t } = useI18n();

const statItems = computed(() => [
    { label: t('admin.dashboard.stats.users'), value: props.stats.users_total, color: '#1677ff', hint: t('admin.dashboard.stats.usersHint') },
    { label: t('admin.dashboard.stats.kycPending'), value: props.stats.kyc_pending, color: '#faad14', hint: t('admin.dashboard.stats.kycPendingHint') },
    { label: t('admin.dashboard.stats.kycApproved'), value: props.stats.kyc_approved, color: '#52c41a', hint: t('admin.dashboard.stats.kycApprovedHint') },
]);
</script>

<template>
    <Head :title="t('admin.dashboard.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.dashboard.title') }}</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.dashboard.cards.overview')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.dashboard.labels.usersCount')">{{ stats.users_total }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.dashboard.labels.kycPending')">
                                <a-typography-text strong type="warning">{{ stats.kyc_pending }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.dashboard.labels.kycApproved')">
                                <a-typography-text strong type="success">{{ stats.kyc_approved }}</a-typography-text>
                            </a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.dashboard.cards.services')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.dashboard.labels.ncanode')">
                                <template v-if="services.ncanode?.enabled">
                                    <a-tag :color="services.ncanode.healthy ? 'success' : 'error'">
                                        {{ services.ncanode.healthy ? t('admin.dashboard.labels.available') : t('admin.dashboard.labels.unavailable') }}
                                    </a-tag>
                                    <span v-if="services.ncanode.skip_verification" class="admin-ant-meta"> (skip verification)</span>
                                </template>
                                <a-tag v-else color="default">{{ t('admin.dashboard.labels.disabled') }}</a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item v-if="services.ncanode?.enabled" label="URL">
                                <span class="admin-ant-meta">{{ services.ncanode.url }}</span>
                            </a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.dashboard.cards.quickActions')" size="small">
                        <a-space direction="vertical" style="width: 100%">
                            <Link v-if="$page.props.adminNav?.sections?.kyc" href="/admin/kyc">
                                <a-button block>{{ t('admin.dashboard.actions.reviewKyc', { count: stats.kyc_pending }) }}</a-button>
                            </Link>
                            <Link v-if="$page.props.adminNav?.sections?.orders" href="/admin/orders">
                                <a-button block>{{ t('admin.dashboard.actions.orders') }}</a-button>
                            </Link>
                            <Link v-if="$page.props.adminNav?.sections?.withdrawals" href="/admin/withdrawals">
                                <a-button block>{{ t('admin.dashboard.actions.withdrawals') }}</a-button>
                            </Link>
                            <Link v-if="$page.props.adminNav?.sections?.wallets" href="/admin/wallets">
                                <a-button block>{{ t('admin.dashboard.actions.wallets') }}</a-button>
                            </Link>
                            <Link v-if="$page.props.auth.canAccessPwa" :href="localizedPath('/wallet')">
                                <a-button block type="dashed">{{ t('admin.dashboard.actions.openApp') }}</a-button>
                            </Link>
                        </a-space>
                    </a-card>
                </a-col>
            </a-row>
        </AdminPage>
    </AdminLayout>
</template>
