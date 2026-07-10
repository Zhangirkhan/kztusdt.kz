<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { applyLocale } from '@/i18n';
import { route } from '../../../vendor/tightenco/ziggy';

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
    /** segment — компактные вкладки; list — список с выбором */
    variant: {
        type: String,
        default: 'segment',
        validator: (value) => ['segment', 'list'].includes(value),
    },
});

const page = usePage();
const { t } = useI18n();
const switchingLocale = ref('');
const pendingLocale = ref('');

const currentLocale = computed(() => pendingLocale.value || page.props.locale?.current || 'ru');
const options = computed(() => page.props.locale?.options ?? []);

const nativeNames = {
    ru: 'Русский',
    kk: 'Қазақша',
    en: 'English',
};

const nativeHints = {
    ru: 'RU',
    kk: 'KK',
    en: 'EN',
};

function optionLabel(option) {
    return nativeNames[option.code] ?? option.label;
}

function optionHint(option) {
    return nativeHints[option.code] ?? String(option.code).toUpperCase();
}

function switchLocale(code) {
    if (code === currentLocale.value || switchingLocale.value) {
        return;
    }

    const previous = page.props.locale?.current ?? 'ru';
    switchingLocale.value = code;
    pendingLocale.value = code;
    applyLocale(code);

    router.post(
        route('locale.update'),
        { locale: code },
        {
            preserveScroll: true,
            onError: () => {
                pendingLocale.value = '';
                applyLocale(previous);
            },
            onFinish: () => {
                switchingLocale.value = '';
                // Keep pending until page.props catches up, then clear.
                if ((page.props.locale?.current ?? '') === code) {
                    pendingLocale.value = '';
                } else {
                    // Inertia may have already navigated; clear after a tick.
                    requestAnimationFrame(() => {
                        pendingLocale.value = '';
                    });
                }
            },
        },
    );
}
</script>

<template>
    <!-- List: radio-style rows for Profile → Language -->
    <div v-if="variant === 'list'" class="w-full">
        <p v-if="showLabel" class="mb-3 text-label-caps uppercase text-text-dim">
            {{ t('locale.label') }}
        </p>
        <div
            class="settings-list"
            role="radiogroup"
            :aria-label="t('locale.label')"
            :aria-busy="Boolean(switchingLocale)"
        >
            <button
                v-for="option in options"
                :key="option.code"
                type="button"
                role="radio"
                class="settings-item w-full"
                :class="switchingLocale === option.code ? 'cursor-wait opacity-70' : ''"
                :aria-checked="option.code === currentLocale"
                :disabled="switchingLocale === option.code"
                @click="switchLocale(option.code)"
            >
                <span class="settings-item__icon text-xs font-bold tracking-wide">
                    {{ optionHint(option) }}
                </span>
                <span class="settings-item__label">{{ optionLabel(option) }}</span>
                <span
                    v-if="option.code === currentLocale"
                    class="material-symbols-outlined text-xl text-accent"
                    aria-hidden="true"
                >
                    check
                </span>
            </button>
        </div>
    </div>

    <!-- Segment: compact tabs (landing / auth / admin) -->
    <div v-else :class="compact ? '' : 'w-full'">
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
                :disabled="switchingLocale === option.code"
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
