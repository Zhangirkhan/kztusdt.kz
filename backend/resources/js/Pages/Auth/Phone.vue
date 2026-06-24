<script setup>
import { computed, onMounted, ref } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/Components/AppLogo.vue';
import SeoHead from '@/Components/SeoHead.vue';
import CompanyIntro from '@/Components/CompanyIntro.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import PwaInstallPrompt from '@/Components/PwaInstallPrompt.vue';
import { useBiometricAuth } from '@/composables/useBiometricAuth';
import {
    clearPhoneMask,
    formatNational,
    getKzPhoneError,
    isKzPhoneComplete,
    MIN_PHONE,
    updatePhoneMask,
} from '@/utils/phoneMask';

const props = defineProps({
    telegramBotUsername: String,
    companyIntro: Object,
});

const { t } = useI18n();
const {
    supported: biometricSupported,
    busy: biometricBusy,
    error: biometricError,
    getSavedPhone,
    checkAvailability,
    loginWithBiometric,
} = useBiometricAuth();

const form = useForm({
    iin: '',
    phone: MIN_PHONE,
});

const phoneInput = ref(null);
const biometricAvailable = ref(false);
const phoneError = computed(() => getKzPhoneError(form.phone, t));
const isIinComplete = computed(() => /^\d{12}$/.test(form.iin));
const iinError = computed(() => {
    if (form.iin.length === 0 || isIinComplete.value) {
        return '';
    }

    return t('auth.iinError');
});
const canSubmit = computed(() => isIinComplete.value && isKzPhoneComplete(form.phone) && !form.processing);
const canClear = computed(() => form.phone !== MIN_PHONE);
const showBiometricLogin = computed(() => biometricSupported && biometricAvailable.value && isKzPhoneComplete(form.phone));

function phoneFromStorage(e164) {
    const digits = String(e164).replace(/\D/g, '');

    if (digits.length === 11 && digits.startsWith('7')) {
        return formatNational(digits.slice(1));
    }

    return MIN_PHONE;
}

async function refreshBiometricAvailability() {
    if (! biometricSupported || ! isKzPhoneComplete(form.phone)) {
        biometricAvailable.value = false;

        return;
    }

    try {
        biometricAvailable.value = await checkAvailability(form.phone);
    } catch {
        biometricAvailable.value = false;
    }
}

async function onBiometricLogin() {
    try {
        await loginWithBiometric(form.phone);
    } catch {
        // error shown via biometricError
    }
}

function syncInput() {
    if (phoneInput.value) {
        phoneInput.value.value = form.phone;
    }
}

function onIinInput(event) {
    form.iin = event.target.value.replace(/\D/g, '').slice(0, 12);
    event.target.value = form.iin;
}

function onPhoneInput(event) {
    form.phone = updatePhoneMask(form.phone, event.target.value);
    syncInput();
    refreshBiometricAvailability();
}

function onPhoneKeydown(event) {
    if (event.key !== 'Backspace' && event.key !== 'Delete') {
        return;
    }

    const input = event.target;
    const { selectionStart, selectionEnd, value } = input;

    if (selectionStart === null || selectionEnd === null) {
        return;
    }

    if (selectionStart !== selectionEnd) {
        return;
    }

    if (event.key === 'Backspace' && selectionStart > 0 && /\D/.test(value[selectionStart - 1])) {
        event.preventDefault();
        form.phone = updatePhoneMask(form.phone, value.slice(0, selectionStart - 1) + value.slice(selectionStart));
        syncInput();
        refreshBiometricAvailability();
    }
}

function clearPhone() {
    form.phone = clearPhoneMask();
    syncInput();
    biometricAvailable.value = false;
    phoneInput.value?.focus();
}

onMounted(() => {
    const savedPhone = getSavedPhone();

    if (savedPhone) {
        form.phone = phoneFromStorage(savedPhone);
        syncInput();
        refreshBiometricAvailability();
    }
});
</script>

<template>
    <SeoHead />

    <div class="mx-auto flex min-h-screen w-full max-w-container-max flex-col bg-background px-margin-page pb-36">
        <header class="flex h-16 items-center justify-between gap-3">
            <AppLogo :size="40" show-wordmark />
            <LocaleSwitcher compact />
        </header>

        <div class="flex flex-1 flex-col justify-center py-stack-section">
            <div class="mb-stack-section text-center">
                <h1 class="text-headline-xl">{{ t('auth.heading') }}</h1>
                <p class="mt-3 text-body-sm text-text-muted">
                    {{ t('auth.subtitle') }}
                </p>
            </div>

            <form class="space-y-stack-element" @submit.prevent="form.post(route('auth.phone.store'))">
                <div>
                    <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('auth.iinLabel') }}</label>
                    <input
                        :value="form.iin"
                        type="text"
                        class="input-field"
                        placeholder="000000000000"
                        autocomplete="off"
                        inputmode="numeric"
                        maxlength="12"
                        @input="onIinInput"
                    />
                    <p v-if="form.errors.iin" class="mt-2 text-sm text-error">{{ form.errors.iin }}</p>
                    <p v-else-if="iinError" class="mt-2 text-sm text-error">{{ iinError }}</p>
                    <p v-else class="mt-2 text-xs text-text-dim">{{ t('auth.iinHint') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('auth.phoneLabel') }}</label>
                    <div class="relative">
                        <input
                            ref="phoneInput"
                            :value="form.phone"
                            type="tel"
                            class="input-field pr-12"
                            placeholder="+7 (707) 123-45-67"
                            autocomplete="tel"
                            inputmode="numeric"
                            maxlength="18"
                            @input="onPhoneInput"
                            @keydown="onPhoneKeydown"
                        />
                        <button
                            v-if="canClear"
                            type="button"
                            class="btn-icon absolute right-3 top-1/2 flex h-8 w-8 -translate-y-1/2 rounded-lg text-text-dim hover:bg-surface-container-high hover:text-on-surface"
                            :aria-label="t('auth.clearPhone')"
                            @click="clearPhone"
                        >
                            <span class="material-symbols-outlined text-xl">close</span>
                        </button>
                    </div>
                    <p v-if="form.errors.phone" class="mt-2 text-sm text-error">{{ form.errors.phone }}</p>
                    <p v-else-if="phoneError" class="mt-2 text-sm text-error">{{ phoneError }}</p>
                    <p v-else class="mt-2 text-xs text-text-dim">
                        {{ t('auth.formatHint') }}
                    </p>
                </div>

                <button type="submit" class="btn-primary" :disabled="!canSubmit">
                    {{ t('auth.submit') }}
                </button>

                <div v-if="showBiometricLogin" class="space-y-3 pt-2">
                    <div class="flex items-center gap-3 text-xs uppercase tracking-wide text-text-dim">
                        <span class="h-px flex-1 bg-outline-variant/30" />
                        <span>{{ t('auth.orDivider') }}</span>
                        <span class="h-px flex-1 bg-outline-variant/30" />
                    </div>

                    <button
                        type="button"
                        class="btn-secondary flex w-full items-center justify-center gap-2"
                        :disabled="biometricBusy"
                        @click="onBiometricLogin"
                    >
                        <span class="material-symbols-outlined text-xl">fingerprint</span>
                        {{ t('auth.biometricLogin') }}
                    </button>

                    <p class="text-center text-xs text-text-dim">
                        {{ t('auth.biometricHint') }}
                    </p>
                </div>

                <p v-if="biometricError" class="text-sm text-error">{{ biometricError }}</p>
            </form>

            <p class="mt-8 text-center text-body-sm text-text-dim">
                {{ t('auth.legalPrefix') }}
                <Link href="/legal/terms" class="text-accent hover:underline">{{ t('auth.terms') }}</Link>,
                <Link href="/legal/privacy" class="text-accent hover:underline">{{ t('auth.privacy') }}</Link>
                {{ t('auth.personalDataPrefix') }}
                <Link href="/legal/personal-data" class="text-accent hover:underline">{{ t('auth.personalData') }}</Link>.
            </p>

            <CompanyIntro variant="compact" :company="companyIntro" class="mt-stack-section" />
        </div>
    </div>

    <PwaInstallPrompt />
</template>
