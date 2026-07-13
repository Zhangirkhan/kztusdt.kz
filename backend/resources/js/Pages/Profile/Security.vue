<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import PinPad from '@/widgets/app-lock/ui/PinPad.vue';
import { useAppLock } from '@/composables/useAppLock';
import { useBiometricAuth } from '@/composables/useBiometricAuth';
import { Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const {
    pinLength,
    configured,
    biometricEnabled,
    changePin,
    setBiometricEnabled,
} = useAppLock();
const { supported: biometricSupported, busy: biometricBusy, registerBiometric } = useBiometricAuth();

const mode = ref('idle');
const currentPin = ref('');
const nextPin = ref('');
const confirmPin = ref('');
const message = ref('');
const error = ref('');
const busy = ref(false);

const statusText = computed(() => {
    if (!configured.value) {
        return t('appLock.security.notConfigured');
    }

    const parts = [t('appLock.security.pinEnabled')];

    if (biometricEnabled.value) {
        parts.push(t('appLock.security.biometricEnabled'));
    }

    return parts.join(' · ');
});

function resetFlow() {
    mode.value = 'idle';
    currentPin.value = '';
    nextPin.value = '';
    confirmPin.value = '';
    error.value = '';
}

function startChangePin() {
    resetFlow();
    mode.value = 'current';
}

async function handlePinComplete(value) {
    error.value = '';
    message.value = '';

    if (mode.value === 'current') {
        currentPin.value = value;
        mode.value = 'next';
        nextPin.value = '';

        return;
    }

    if (mode.value === 'next') {
        nextPin.value = value;
        mode.value = 'confirm';
        confirmPin.value = '';

        return;
    }

    if (value !== nextPin.value) {
        error.value = t('appLock.errors.pinMismatch');
        mode.value = 'next';
        nextPin.value = '';
        confirmPin.value = '';

        return;
    }

    busy.value = true;

    try {
        const changed = await changePin(currentPin.value, nextPin.value);

        if (!changed) {
            error.value = t('appLock.errors.invalidPin');
            mode.value = 'current';
            currentPin.value = '';
            nextPin.value = '';
            confirmPin.value = '';

            return;
        }

        message.value = t('appLock.security.pinChanged');
        resetFlow();
    } finally {
        busy.value = false;
    }
}

const activePin = computed({
    get: () => {
        if (mode.value === 'current') {
            return currentPin.value;
        }

        if (mode.value === 'next') {
            return nextPin.value;
        }

        if (mode.value === 'confirm') {
            return confirmPin.value;
        }

        return '';
    },
    set: (value) => {
        if (mode.value === 'current') {
            currentPin.value = value;
        } else if (mode.value === 'next') {
            nextPin.value = value;
        } else if (mode.value === 'confirm') {
            confirmPin.value = value;
        }
    },
});

const flowTitle = computed(() => {
    if (mode.value === 'current') {
        return t('appLock.security.enterCurrentPin');
    }

    if (mode.value === 'next') {
        return t('appLock.security.enterNewPin');
    }

    if (mode.value === 'confirm') {
        return t('appLock.security.confirmNewPin');
    }

    return '';
});

async function toggleBiometric() {
    if (!biometricSupported || !configured.value) {
        return;
    }

    busy.value = true;
    error.value = '';
    message.value = '';

    try {
        if (biometricEnabled.value) {
            setBiometricEnabled(false);
            message.value = t('appLock.security.biometricDisabled');
        } else {
            await registerBiometric(t('appLock.biometricKeyName'));
            setBiometricEnabled(true);
            message.value = t('appLock.security.biometricEnabledMessage');
        }
    } catch (exception) {
        error.value = exception?.message ?? t('appLock.biometricFailed');
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <Head :title="t('security.title')" />

    <ExchangeLayout>
        <template #title>{{ t('security.title') }}</template>

        <ProfileSettingsShell>
            <section class="card">
                <div class="flex items-start gap-3">
                    <span class="settings-item__icon">
                        <span class="material-symbols-outlined">lock</span>
                    </span>
                    <div class="flex-1">
                        <p class="font-semibold text-on-surface">{{ t('appLock.security.title') }}</p>
                        <p class="mt-1 text-sm text-text-muted">{{ statusText }}</p>
                        <p class="mt-1 text-sm text-text-muted">{{ t('appLock.security.hint') }}</p>

                        <div v-if="mode === 'idle'" class="mt-4 flex flex-wrap gap-3">
                            <button
                                v-if="configured"
                                type="button"
                                class="btn-secondary"
                                @click="startChangePin"
                            >
                                {{ t('appLock.security.changePin') }}
                            </button>

                            <button
                                v-if="configured && biometricSupported"
                                type="button"
                                class="btn-secondary"
                                :disabled="busy || biometricBusy"
                                @click="toggleBiometric"
                            >
                                {{ biometricEnabled ? t('appLock.security.disableBiometric') : t('appLock.security.enableBiometric') }}
                            </button>
                        </div>

                        <div v-else class="mt-4">
                            <p class="mb-3 text-sm font-semibold text-on-surface">{{ flowTitle }}</p>
                            <PinPad
                                v-model="activePin"
                                :length="pinLength"
                                :disabled="busy"
                                @complete="handlePinComplete"
                            />
                            <button type="button" class="mt-4 text-sm font-semibold text-accent" @click="resetFlow">
                                {{ t('common.cancel') }}
                            </button>
                        </div>

                        <p v-if="message" class="mt-3 text-sm text-accent">{{ message }}</p>
                        <p v-if="error" class="mt-3 text-sm text-error">{{ error }}</p>
                    </div>
                </div>
            </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
