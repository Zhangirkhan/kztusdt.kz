<script setup>
import PinPad from '@/widgets/app-lock/ui/PinPad.vue';
import { useAppLock } from '@/composables/useAppLock';
import { useBiometricAuth } from '@/composables/useBiometricAuth';
import { computed, ref, watch, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    mode: {
        type: String,
        default: 'unlock',
    },
});

const emit = defineEmits(['setup-complete']);

const { t } = useI18n();
const {
    pinLength,
    needsSetup,
    configured,
    biometricEnabled,
    setupPin,
    verifyAppPin,
    setBiometricEnabled,
    unlock,
} = useAppLock();
const { supported: biometricSupported, busy: biometricBusy, unlockWithBiometric, registerBiometric } = useBiometricAuth();

const pin = ref('');
const confirmPin = ref('');
const setupStep = ref('create');
const error = ref('');
const busy = ref(false);

const isSetupMode = computed(() => props.mode === 'setup' || needsSetup.value);
const showBiometricButton = computed(() =>
    !isSetupMode.value
    && configured.value
    && biometricEnabled.value
    && biometricSupported,
);

const title = computed(() => {
    if (isSetupMode.value) {
        if (setupStep.value === 'confirm') {
            return t('appLock.setup.confirmTitle');
        }

        if (setupStep.value === 'biometric') {
            return t('appLock.setup.biometricTitle');
        }

        return t('appLock.setup.createTitle');
    }

    return t('appLock.unlock.title');
});

const subtitle = computed(() => {
    if (isSetupMode.value) {
        if (setupStep.value === 'confirm') {
            return t('appLock.setup.confirmHint');
        }

        if (setupStep.value === 'biometric') {
            return t('appLock.setup.biometricHint');
        }

        return t('appLock.setup.createHint');
    }

    return t('appLock.unlock.hint');
});

const activePin = computed({
    get: () => (setupStep.value === 'confirm' ? confirmPin.value : pin.value),
    set: (value) => {
        if (setupStep.value === 'confirm') {
            confirmPin.value = value;
        } else {
            pin.value = value;
        }
    },
});

watch(() => props.mode, () => {
    pin.value = '';
    confirmPin.value = '';
    setupStep.value = 'create';
    error.value = '';
});

async function handleComplete(value) {
    error.value = '';

    if (isSetupMode.value) {
        await handleSetup(value);

        return;
    }

    busy.value = true;

    try {
        const valid = await verifyAppPin(value);

        if (!valid) {
            error.value = t('appLock.errors.invalidPin');
            pin.value = '';
        }
    } finally {
        busy.value = false;
    }
}

async function handleSetup(value) {
    if (setupStep.value === 'create') {
        pin.value = value;
        setupStep.value = 'confirm';
        confirmPin.value = '';

        return;
    }

    if (value !== pin.value) {
        error.value = t('appLock.errors.pinMismatch');
        confirmPin.value = '';
        setupStep.value = 'create';
        pin.value = '';

        return;
    }

    if (biometricSupported) {
        setupStep.value = 'biometric';

        return;
    }

    await finalizeSetup(false);
}

async function finalizeSetup(enableBiometric) {
    busy.value = true;
    error.value = '';

    try {
        await setupPin(pin.value, enableBiometric);
        pin.value = '';
        confirmPin.value = '';
        emit('setup-complete');
    } finally {
        busy.value = false;
    }
}

async function enableBiometricUnlock() {
    busy.value = true;
    error.value = '';

    try {
        await registerBiometric(t('appLock.biometricKeyName'));
        setBiometricEnabled(true);
        await finalizeSetup(true);
    } catch (exception) {
        error.value = exception?.message ?? t('appLock.biometricFailed');
    } finally {
        busy.value = false;
    }
}

async function tryBiometricUnlock() {
    busy.value = true;
    error.value = '';

    try {
        await unlockWithBiometric();
        pin.value = '';
        unlock();
    } catch (exception) {
        error.value = exception?.message ?? t('appLock.biometricFailed');
    } finally {
        busy.value = false;
    }
}

function skipBiometricSetup() {
    finalizeSetup(false);
}

onMounted(() => {
    if (!isSetupMode.value && showBiometricButton.value) {
        tryBiometricUnlock();
    }
});
</script>

<template>
    <div class="app-lock-overlay">
        <div class="app-lock-overlay__panel">
            <div class="app-lock-overlay__icon">
                <span class="material-symbols-outlined">
                    {{ setupStep === 'biometric' ? 'fingerprint' : 'lock' }}
                </span>
            </div>

            <h2 class="app-lock-overlay__title">{{ title }}</h2>
            <p class="app-lock-overlay__subtitle">{{ subtitle }}</p>

            <template v-if="setupStep !== 'biometric'">
                <PinPad
                    v-model="activePin"
                    :length="pinLength"
                    :disabled="busy || biometricBusy"
                    @complete="handleComplete"
                />

                <p v-if="error" class="app-lock-overlay__error">{{ error }}</p>

                <button
                    v-if="showBiometricButton"
                    type="button"
                    class="btn-secondary app-lock-overlay__biometric"
                    :disabled="busy || biometricBusy"
                    @click="tryBiometricUnlock"
                >
                    {{ t('appLock.unlock.useBiometric') }}
                </button>
            </template>

            <template v-else>
                <button
                    type="button"
                    class="btn-primary app-lock-overlay__biometric"
                    :disabled="busy || biometricBusy"
                    @click="enableBiometricUnlock"
                >
                    {{ t('appLock.setup.enableBiometric') }}
                </button>
                <button
                    type="button"
                    class="btn-secondary app-lock-overlay__biometric"
                    :disabled="busy || biometricBusy"
                    @click="skipBiometricSetup"
                >
                    {{ t('appLock.setup.skipBiometric') }}
                </button>
                <p v-if="error" class="app-lock-overlay__error">{{ error }}</p>
            </template>
        </div>
    </div>
</template>

<style scoped>
.app-lock-overlay {
    position: fixed;
    inset: 0;
    z-index: 2000;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
    background: rgba(15, 23, 42, 0.92);
    backdrop-filter: blur(8px);
}

.app-lock-overlay__panel {
    width: 100%;
    max-width: 360px;
    padding: 28px 20px 20px;
    border-radius: 24px;
    background: var(--color-surface, #fff);
    color: var(--color-on-surface, #0f172a);
    box-shadow: 0 24px 64px rgba(15, 23, 42, 0.28);
}

.app-lock-overlay__icon {
    display: flex;
    justify-content: center;
    margin-bottom: 16px;
}

.app-lock-overlay__icon .material-symbols-outlined {
    font-size: 42px;
    color: var(--color-accent, #2563eb);
}

.app-lock-overlay__title {
    margin: 0;
    text-align: center;
    font-size: 22px;
    font-weight: 700;
}

.app-lock-overlay__subtitle {
    margin: 8px 0 24px;
    text-align: center;
    font-size: 14px;
    color: var(--color-text-muted, #64748b);
    line-height: 1.45;
}

.app-lock-overlay__error {
    margin: 16px 0 0;
    text-align: center;
    font-size: 13px;
    color: var(--color-error, #dc2626);
}

.app-lock-overlay__biometric {
    width: 100%;
    margin-top: 12px;
}
</style>
