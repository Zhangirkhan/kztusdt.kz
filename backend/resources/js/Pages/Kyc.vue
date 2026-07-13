<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import KycFlow from '@/widgets/kyc-flow/ui/KycFlow.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import { Head, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    profile: Object,
    kycStatus: String,
    rejectionReason: String,
    provider: { type: String, default: 'manual' },
    manualEnabled: { type: Boolean, default: true },
    showAitu: { type: Boolean, default: false },
    showSumsub: { type: Boolean, default: false },
    showManualForm: { type: Boolean, default: true },
    aituVerifyUrl: { type: String, default: null },
    aituKycScopeConfigured: { type: Boolean, default: true },
    iinMismatch: { type: Boolean, default: false },
});

const page = usePage();
const { t } = useI18n();
</script>

<template>
    <Head :title="t('kyc.title')" />

    <ExchangeLayout>
        <template #title>{{ t('kyc.layoutTitle') }}</template>

        <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />
        <FlashBanner v-if="page.props.errors?.form" :message="page.props.errors.form" tone="error" />

        <KycFlow v-bind="$props" />
    </ExchangeLayout>
</template>
