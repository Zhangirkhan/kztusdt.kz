<script setup>
import KycManualForm from '@/features/kyc-manual-form/ui/KycManualForm.vue';
import { pendingReviewHint } from '@/entities/kyc/lib/pendingReviewHint';
import { Link } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';

const SumsubKycWidget = defineAsyncComponent(() => import('@/Components/SumsubKycWidget.vue'));

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
</script>

<template>
    <section class="card mb-stack-element">
        <p class="text-label-caps uppercase text-text-dim">Статус</p>
        <p class="mt-2 text-headline-md capitalize text-accent">{{ kycStatus }}</p>
        <p v-if="rejectionReason" class="mt-2 text-sm text-error">{{ rejectionReason }}</p>
        <p v-if="kycStatus === 'pending_review'" class="mt-2 text-body-sm text-text-muted">
            {{ pendingReviewHint({ profileProvider: profile?.provider, manualEnabled, provider }) }}
        </p>
    </section>

    <section v-if="showSumsub" class="card mb-stack-element">
        <SumsubKycWidget container-id="kyc-page-sumsub" :kyc-status="kycStatus" />
    </section>

    <section v-if="showAitu" class="card mb-stack-element space-y-4">
        <p class="text-body-sm text-text-muted">
            Быстрая верификация через Aitu Passport. Нажмите кнопку — вы перейдёте в Aitu,
            подтвердите личность, и после возврата статус обновится автоматически.
        </p>
        <p v-if="!aituKycScopeConfigured" class="text-sm text-accent">
            Автоматическая проверка KYC через Aitu пока не подключена. Используйте ручную подачу документов ниже.
        </p>
        <a v-if="aituVerifyUrl" :href="aituVerifyUrl" class="btn-primary inline-block">Пройти верификацию через Aitu</a>
    </section>

    <KycManualForm v-if="showManualForm" :profile="profile" :show-aitu="showAitu" />

    <div v-if="kycStatus === 'approved'" class="card text-accent">
        KYC одобрен. Кошелёк будет доступен после создания адреса.
        <Link :href="route('wallet')" class="mt-3 block font-semibold">Перейти в кошелёк →</Link>
    </div>

    <div v-else-if="kycStatus === 'pending_review' && !showManualForm" class="card text-body-sm text-text-muted">
        Заявка отправлена. Дождитесь решения службы безопасности.
    </div>
</template>
