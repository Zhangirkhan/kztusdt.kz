<script setup>
import DueDiligenceForm from '@/features/due-diligence-form/ui/DueDiligenceForm.vue';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const page = usePage();
const { t } = useI18n();

const dueDiligence = computed(() => page.props.dueDiligence ?? {});
const visible = computed(() => Boolean(dueDiligence.value.blocking));
</script>

<template>
    <div v-if="visible" class="due-diligence-overlay">
        <div class="due-diligence-overlay__panel">
            <header class="due-diligence-overlay__header">
                <div class="due-diligence-overlay__icon">
                    <span class="material-symbols-outlined" aria-hidden="true">assignment</span>
                </div>
                <h2 class="due-diligence-overlay__title">{{ t('dueDiligence.blockingTitle') }}</h2>
                <p class="due-diligence-overlay__subtitle">{{ t('dueDiligence.blockingSubtitle') }}</p>
            </header>

            <DueDiligenceForm
                v-if="dueDiligence.options"
                :options="dueDiligence.options"
                compact
            />
        </div>
    </div>
</template>

<style scoped>
.due-diligence-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: max(16px, env(safe-area-inset-top)) 16px max(16px, env(safe-area-inset-bottom));
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(8px);
}

.due-diligence-overlay__panel {
    display: flex;
    flex-direction: column;
    width: 100%;
    max-width: 420px;
    max-height: min(680px, calc(100dvh - 32px));
    padding: 20px 16px 16px;
    border-radius: 24px;
    background: var(--color-surface, #fff);
    color: var(--color-text, #0f172a);
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.28);
}

.due-diligence-overlay__header {
    flex-shrink: 0;
    margin-bottom: 16px;
}

.due-diligence-overlay__icon {
    display: flex;
    justify-content: center;
    margin-bottom: 10px;
}

.due-diligence-overlay__icon .material-symbols-outlined {
    font-size: 40px;
    color: var(--color-accent, #2563eb);
}

.due-diligence-overlay__title {
    margin: 0;
    text-align: center;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.25;
}

.due-diligence-overlay__subtitle {
    margin: 8px 0 0;
    text-align: center;
    font-size: 14px;
    line-height: 1.45;
    color: var(--color-text-muted, #64748b);
}
</style>
