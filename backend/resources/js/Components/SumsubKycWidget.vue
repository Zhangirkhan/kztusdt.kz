<script setup>
import { onMounted } from 'vue';
import { useSumsubKyc } from '@/composables/useSumsubKyc';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    kycStatus: { type: String, default: 'none' },
    containerId: { type: String, required: true },
    compact: { type: Boolean, default: false },
});

const emit = defineEmits(['approved', 'pending', 'rejected']);
const { t } = useI18n();

const { error, loading, notice, currentStep, launch } = useSumsubKyc({
    onApproved: (data) => emit('approved', data),
    onPending: (data) => emit('pending', data),
    onRejected: (data) => emit('rejected', data),
});

onMounted(() => {
    if (props.kycStatus !== 'approved') {
        launch(props.containerId);
    }
});
</script>

<template>
    <div class="sumsub-kyc-widget">
        <p v-if="loading" class="flex items-center gap-2 text-body-sm text-text-muted">
            <span class="material-symbols-outlined animate-spin text-base">progress_activity</span>
            {{ t('sumsub.widget.loading') }}
        </p>
        <p v-if="currentStep && !compact" class="mb-3 text-sm font-medium text-accent">
            {{ currentStep }}
        </p>
        <p v-if="notice" class="mb-3 text-sm text-accent">{{ notice }}</p>
        <p v-if="error" class="mb-3 text-sm text-error">{{ error }}</p>
        <p v-if="!error && kycStatus !== 'approved'" class="mb-3 text-xs text-text-dim">
            {{ t('sumsub.widget.hint') }}
        </p>
        <div :id="containerId" class="sumsub-kyc-container" />
    </div>
</template>

<style scoped>
.sumsub-kyc-container {
    min-height: 28rem;
    width: 100%;
}

.sumsub-kyc-container :deep(iframe) {
    width: 100%;
    min-height: 28rem;
    border: 0;
}
</style>
