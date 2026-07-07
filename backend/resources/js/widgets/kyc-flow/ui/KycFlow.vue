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
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-label-caps uppercase tracking-wide text-text-dim">Статус</p>
                <p class="mt-2 text-headline-md capitalize text-on-surface">{{ kycStatus }}</p>
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

    <section v-if="showSumsub" class="card mb-stack-element">
        <SumsubKycWidget container-id="kyc-page-sumsub" :kyc-status="kycStatus" />
    </section>

    <section v-if="showAitu" class="card mb-stack-element space-y-4">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5 text-2xl text-accent">verified_user</span>
            <div class="min-w-0">
                <p class="text-sm font-semibold text-on-surface">Быстрая верификация через Aitu</p>
                <p class="mt-1 text-body-sm text-text-muted">
                    Вы перейдёте в Aitu Passport, подтвердите личность и вернётесь обратно — статус обновится автоматически.
                </p>
            </div>
        </div>
        <p v-if="!aituKycScopeConfigured" class="text-sm text-accent">
            Автоматическая проверка KYC через Aitu пока не подключена. Используйте ручную подачу документов ниже.
        </p>
        <div v-if="aituVerifyUrl" class="space-y-2">
            <a :href="aituVerifyUrl" class="btn-primary block text-center">
                <span class="block text-base font-semibold">Перейти в Aitu</span>
            </a>
            <p class="text-center text-xs font-semibold text-text-muted">Верификация займёт ~1 минуту</p>
        </div>
    </section>

    <KycManualForm v-if="showManualForm" :profile="profile" :show-aitu="showAitu" />

    <section v-if="kycStatus === 'approved'" class="card border border-green-200 bg-green-50">
        <div class="flex items-start gap-3">
            <span class="material-symbols-outlined mt-0.5 text-2xl text-green-600">check_circle</span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-on-surface">KYC одобрен</p>
                <p class="mt-1 text-body-sm text-text-muted">
                    Кошелёк будет доступен после создания адреса.
                </p>
                <Link
                    :href="route('wallet')"
                    class="btn-primary mt-4 block text-center no-underline"
                >
                    Перейти в кошелёк
                </Link>
            </div>
        </div>
    </section>

    <div v-else-if="kycStatus === 'pending_review' && !showManualForm" class="card text-body-sm text-text-muted">
        Заявка отправлена. Дождитесь решения службы безопасности.
    </div>
</template>
