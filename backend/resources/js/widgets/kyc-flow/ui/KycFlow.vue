<script setup>
import KycManualForm from '@/features/kyc-manual-form/ui/KycManualForm.vue';
import KycIinMismatchForm from '@/features/kyc-iin-mismatch/ui/KycIinMismatchForm.vue';
import { pendingReviewHint } from '@/entities/kyc/lib/pendingReviewHint';
import { Link } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { navigateAfterAuth } from '@/utils/authNavigation';

const SumsubKycWidget = defineAsyncComponent(() => import('@/Components/SumsubKycWidget.vue'));

const props = defineProps({
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
const { t } = useI18n();
const kycStatusLabel = computed(() => t(`kyc.flow.status.${props.kycStatus}`));
const showIinMismatch = ref(props.iinMismatch);

function onSumsubApproved(data) {
    if (data?.iin_mismatch) {
        showIinMismatch.value = true;
    }
}

function onIinConfirmed() {
    showIinMismatch.value = false;
    navigateAfterAuth('/wallet');
}
</script>

<template>
    <section class="card mb-stack-element">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-label-caps uppercase tracking-wide text-text-dim">{{ t('kyc.flow.statusLabel') }}</p>
                <p class="mt-2 text-headline-md text-on-surface">{{ kycStatusLabel }}</p>
            </div>
            <span
                class="shrink-0 rounded-full border border-outline-variant/60 bg-surface-container-high px-3 py-1 text-xs font-semibold text-text-muted"
            >
                KYC
            </span>
        </div>
        <p v-if="rejectionReason" class="mt-2 text-sm text-error">{{ rejectionReason }}</p>
        <p v-if="kycStatus === 'pending_review'" class="mt-2 text-body-sm text-text-muted">
            {{ pendingReviewHint({ profileProvider: profile?.provider, manualEnabled, provider }) }}
        </p>
    </section>

    <KycIinMismatchForm
        v-if="showIinMismatch"
        class="mb-stack-element"
        @confirmed="onIinConfirmed"
    />

    <section v-if="showSumsub && !showIinMismatch" class="card mb-stack-element">
        <SumsubKycWidget
            container-id="kyc-page-sumsub"
            :kyc-status="kycStatus"
            @approved="onSumsubApproved"
        />
    </section>

    <section v-if="showAitu && !showIinMismatch" class="card mb-stack-element space-y-4">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5 text-2xl text-accent">verified_user</span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-on-surface">{{ t('kyc.flow.aitu.title') }}</p>
                <p class="mt-1 text-body-sm text-text-muted">
                    {{ t('kyc.flow.aitu.subtitle') }}
                </p>
            </div>
        </div>
        <p v-if="!aituKycScopeConfigured" class="text-sm text-accent">
            {{ t('kyc.flow.aitu.notConfigured') }}
        </p>
        <div v-if="aituVerifyUrl" class="space-y-2">
            <a :href="aituVerifyUrl" class="btn-primary block text-center">
                <span class="block text-base font-semibold">{{ t('kyc.flow.aitu.action') }}</span>
            </a>
            <p class="text-center text-xs font-semibold text-text-muted">{{ t('kyc.flow.aitu.hint') }}</p>
        </div>
    </section>

    <KycManualForm v-if="showManualForm && !showIinMismatch" :profile="profile" :show-aitu="showAitu" />

    <section v-if="kycStatus === 'approved' && !showIinMismatch" class="card border border-green-200 bg-green-50">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5 text-2xl text-green-600">check_circle</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-on-surface">{{ t('kyc.flow.approved.title') }}</p>
                <p class="mt-1 text-body-sm text-text-muted">
                    {{ t('kyc.flow.approved.subtitle') }}
                </p>
                <Link
                    :href="route('wallet')"
                    class="btn-primary mt-4 block text-center no-underline"
                >
                    {{ t('kyc.flow.approved.action') }}
                </Link>
            </div>
        </div>
    </section>

    <div v-else-if="kycStatus === 'pending_review' && !showManualForm && !showIinMismatch" class="card text-body-sm text-text-muted">
        {{ t('kyc.flow.pendingNoManual') }}
    </div>
</template>
