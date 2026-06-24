<script setup>
import { computed, onMounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePushNotifications } from '@/composables/usePushNotifications';

const DISMISS_KEY = 'push-optin-dismissed';
const DISMISS_DAYS = 7;

const { t } = useI18n();
const {
    supported,
    configured,
    busy,
    permission,
    subscribe,
    syncIfGranted,
} = usePushNotifications();

const dismissed = ref(true);

function wasDismissedRecently() {
    const at = localStorage.getItem(DISMISS_KEY);

    if (! at) {
        return false;
    }

    return (Date.now() - Number(at)) / (1000 * 60 * 60 * 24) < DISMISS_DAYS;
}

const visible = computed(
    () => supported && configured.value && permission.value === 'default' && ! dismissed.value,
);

async function enable() {
    const ok = await subscribe();

    if (ok) {
        dismissed.value = true;
    }
}

function dismiss() {
    localStorage.setItem(DISMISS_KEY, String(Date.now()));
    dismissed.value = true;
}

onMounted(() => {
    dismissed.value = wasDismissedRecently();
    syncIfGranted();
});
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="-translate-y-2 opacity-0"
        enter-to-class="translate-y-0 opacity-100"
    >
        <div
            v-if="visible"
            class="mb-stack-element flex items-start gap-3 rounded-2xl border border-outline-variant/40 bg-surface-container-high/60 p-4"
        >
            <span class="material-symbols-outlined mt-0.5 text-2xl text-accent">notifications_active</span>

            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-on-surface">{{ t('push.title') }}</p>
                <p class="mt-1 text-body-sm text-text-muted">{{ t('push.body') }}</p>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-lg bg-accent px-4 py-2 text-sm font-semibold text-on-accent transition active:scale-[0.98] disabled:opacity-60"
                        :disabled="busy"
                        @click="enable"
                    >
                        {{ t('push.enable') }}
                    </button>
                    <button
                        type="button"
                        class="rounded-lg px-4 py-2 text-sm font-semibold text-text-dim transition hover:text-on-surface"
                        @click="dismiss"
                    >
                        {{ t('push.later') }}
                    </button>
                </div>
            </div>

            <button
                type="button"
                class="shrink-0 p-1 text-text-dim transition hover:text-on-surface"
                :aria-label="t('push.later')"
                @click="dismiss"
            >
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>
    </Transition>
</template>
