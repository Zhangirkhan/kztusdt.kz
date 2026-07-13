<script setup>
import { computed } from 'vue';
import AppLogo from '@/Components/AppLogo.vue';
import { usePwaInstall } from '@/composables/usePwaInstall';
import { useI18n } from 'vue-i18n';

const { canInstall, showIosHint, dismiss, install } = usePwaInstall();
const { t } = useI18n();

const visible = computed(() => canInstall.value || showIosHint.value);
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="visible"
            class="fixed inset-x-0 bottom-0 z-50 border-t border-outline-variant/40 bg-surface/95 px-margin-page py-4 backdrop-blur-md"
        >
            <div class="mx-auto flex max-w-container-max items-start gap-3">
                <AppLogo />

                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-on-surface">{{ t('pwa.prompt.title') }}</p>
                    <p v-if="canInstall" class="mt-1 text-body-sm text-text-muted">
                        {{ t('pwa.prompt.installableHint') }}
                    </p>
                    <p v-else class="mt-1 text-body-sm text-text-muted">
                        {{ t('pwa.prompt.iosHint') }}
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <button
                            v-if="canInstall"
                            type="button"
                            class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent transition active:scale-[0.98]"
                            @click="install"
                        >
                            {{ t('pwa.prompt.install') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-lg px-4 py-2 text-sm font-semibold text-text-dim transition hover:text-on-surface"
                            @click="dismiss"
                        >
                            {{ t('pwa.prompt.dismiss') }}
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="shrink-0 p-1 text-text-dim transition hover:text-on-surface"
                    :aria-label="t('pwa.prompt.closeAria')"
                    @click="dismiss"
                >
                    <span class="material-symbols-outlined text-xl">close</span>
                </button>
            </div>
        </div>
    </Transition>
</template>
