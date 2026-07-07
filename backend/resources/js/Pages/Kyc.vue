<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import KycFlow from '@/widgets/kyc-flow/ui/KycFlow.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import { Head, usePage } from '@inertiajs/vue3';

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
});

const page = usePage();
</script>

<template>
    <Head title="KYC" />

    <ExchangeLayout>
        <template #title>KYC верификация</template>

        <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />
        <FlashBanner v-if="page.props.errors?.form" :message="page.props.errors.form" tone="error" />

        <KycFlow v-bind="$props" />
    </ExchangeLayout>
</template>
