<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    settings: Object,
    note: String,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('admin.settings.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.settings.title') }}</template>

        <AdminPage>
            <a-typography-text type="secondary" class="admin-ant-block">{{ note }}</a-typography-text>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.settings.cards.fees')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.settings.labels.defaultFee')">{{ settings.fees.default }}%</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.subscriptionFee')">{{ settings.fees.subscription }}%</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.settings.cards.limits')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.settings.labels.minBuy')">{{ settings.limits.min_buy_kzt }} ₸</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.maxBuy')">{{ settings.limits.max_buy_kzt }} ₸</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.minSell')">{{ settings.limits.min_sell_usdt }} USDT</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.maxSell')">{{ settings.limits.max_sell_usdt }} USDT</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.settings.cards.rate')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.settings.labels.buyRate')">{{ settings.rate.buy }} ₸</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.sellRate')">{{ settings.rate.sell }} ₸</a-descriptions-item>
                            <a-descriptions-item label="Markup buy">{{ settings.rate.markup_buy }}</a-descriptions-item>
                            <a-descriptions-item label="Markup sell">{{ settings.rate.markup_sell }}</a-descriptions-item>
                            <a-descriptions-item label="Fallback">{{ settings.rate.fallback }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.stale')">{{ settings.rate.stale ? t('admin.shared.boolean.yes') : t('admin.shared.boolean.no') }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.updatedAt')">{{ settings.rate.updated_at || t('admin.shared.empty') }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.settings.cards.features')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.settings.labels.withdrawals')">
                                <a-tag :color="settings.features.withdrawals_enabled ? 'success' : 'default'">
                                    {{ settings.features.withdrawals_enabled ? t('admin.shared.boolean.enabled') : t('admin.shared.boolean.disabled') }}
                                </a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.sweep')">
                                <a-tag :color="settings.features.sweep_enabled ? 'success' : 'default'">
                                    {{ settings.features.sweep_enabled ? t('admin.shared.boolean.enabledSingular') : t('admin.shared.boolean.disabledSingular') }}
                                </a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.settings.labels.kycProvider')">{{ settings.features.kyc_provider }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>
            </a-row>
        </AdminPage>
    </AdminLayout>
</template>
