<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
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
    codeOnly: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const { t } = useI18n();
const switchingLocale = ref('');

const currentLocale = computed(() => page.props.locale?.current ?? 'ru');
const options = computed(() => page.props.locale?.options ?? []);

function switchLocale(code) {
    if (code === currentLocale.value || switchingLocale.value) {
        return;
    }

    switchingLocale.value = code;

    router.post(
        route('locale.update'),
        { locale: code },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => {
                switchingLocale.value = code;
            },
            onSuccess: () => applyLocale(code),
            onFinish: () => {
                switchingLocale.value = '';
            },
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
            class="inline-flex items-stretch rounded-xl border border-outline-variant/70 bg-surface-container-high p-0.5"
            :class="compact ? '' : 'w-full'"
            role="group"
            :aria-label="t('locale.label')"
            :aria-busy="Boolean(switchingLocale)"
        >
            <button
                v-for="option in options"
                :key="option.code"
                type="button"
                class="flex min-h-9 items-center justify-center rounded-lg border px-3 py-1.5 text-sm font-semibold uppercase transition"
                :class="[
                    compact ? (codeOnly ? 'min-w-11' : 'min-w-[5.5rem]') : 'flex-1',
                    option.code === currentLocale
                        ? 'border-outline-variant bg-background text-on-surface'
                        : 'border-transparent text-text-dim hover:bg-surface-container-low hover:text-on-surface',
                    switchingLocale === option.code ? 'cursor-wait opacity-70' : '',
                ]"
                :disabled="Boolean(switchingLocale)"
                :aria-pressed="option.code === currentLocale"
                @click="switchLocale(option.code)"
            >
                <span class="whitespace-nowrap">
                    {{ codeOnly ? option.code : option.label }}
                </span>
            </button>
        </div>
    </div>
</template>
