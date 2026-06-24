<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { applyLocale } from '@/i18n';

const props = defineProps({
    showLabel: {
        type: Boolean,
        default: false,
    },
    compact: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const { t } = useI18n();

const currentLocale = computed(() => page.props.locale?.current ?? 'ru');
const options = computed(() => page.props.locale?.options ?? []);

function switchLocale(code) {
    if (code === currentLocale.value) {
        return;
    }

    router.post(
        route('locale.update'),
        { locale: code },
        {
            preserveScroll: true,
            onSuccess: () => applyLocale(code),
        },
    );
}
</script>

<template>
    <div :class="compact ? '' : 'w-full'">
        <p v-if="showLabel" class="mb-2 text-label-caps uppercase text-text-dim">
            {{ t('locale.label') }}
        </p>
        <div
            class="inline-flex rounded-xl border border-outline-variant/40 bg-surface-container-low p-1"
            :class="compact ? '' : 'w-full'"
            role="group"
            :aria-label="t('locale.label')"
        >
            <button
                v-for="option in options"
                :key="option.code"
                type="button"
                class="rounded-lg px-3 py-2 text-sm font-semibold transition"
                :class="[
                    compact ? 'min-w-[3.25rem]' : 'flex-1',
                    option.code === currentLocale
                        ? 'bg-accent text-on-accent'
                        : 'text-text-dim hover:bg-surface-container-high hover:text-on-surface',
                ]"
                @click="switchLocale(option.code)"
            >
                {{ option.code.toUpperCase() }}
            </button>
        </div>
    </div>
</template>
