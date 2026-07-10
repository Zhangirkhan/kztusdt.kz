<script setup>
import { computed, onMounted, onUnmounted, ref, toRef } from 'vue';
import { router, useForm, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/Components/AppLogo.vue';
import SeoHead from '@/Components/SeoHead.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import PwaInstallPrompt from '@/Components/PwaInstallPrompt.vue';
import { useBiometricAuth } from '@/composables/useBiometricAuth';
import { useNcaLayer } from '@/composables/useNcaLayer';
import { usePhoneMaskInput } from '@/composables/usePhoneMaskInput';
import {
    formatNational,
    getKzPhoneError,
    isKzPhoneComplete,
    MIN_PHONE,
} from '@/utils/phoneMask';

const props = defineProps({
    companyIntro: Object,
    legalEntityEdsRequired: {
        type: Boolean,
        default: true,
    },
});

const { t } = useI18n();
const { signBase64Detached } = useNcaLayer();
const {
    supported: biometricSupported,
    busy: biometricBusy,
    error: biometricError,
    getSavedPhone,
    checkAvailability,
    loginWithBiometric,
} = useBiometricAuth();

const form = useForm({
    client_type: 'individual',
    iin: '',
    bin: '',
    company_name: '',
    phone: MIN_PHONE,
});

const edsStep = ref('form');
const edsBusy = ref(false);
const edsError = ref('');
const edsSession = ref(null);

const isLegalEntity = computed(() => form.client_type === 'legal_entity');
const showEdsStep = computed(() => isLegalEntity.value && props.legalEntityEdsRequired && edsStep.value === 'sign');
const biometricAvailable = ref(false);
let biometricCheckTimer = null;
const phoneError = computed(() => getKzPhoneError(form.phone, t));
const isIinComplete = computed(() => /^\d{12}$/.test(form.iin));
const iinError = computed(() => {
    if (isLegalEntity.value || form.iin.length === 0 || isIinComplete.value) {
        return '';
    }

    return t('auth.iinError');
});
const canSubmit = computed(() => {
    if (isLegalEntity.value) {
        return false;
    }

    return isIinComplete.value && isKzPhoneComplete(form.phone) && !form.processing;
});
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
    if (biometricCheckTimer) {
        clearTimeout(biometricCheckTimer);
    }

    biometricCheckTimer = setTimeout(async () => {
        if (! biometricSupported || ! isKzPhoneComplete(form.phone)) {
            biometricAvailable.value = false;

            return;
        }

        try {
            biometricAvailable.value = await checkAvailability(form.phone);
        } catch {
            biometricAvailable.value = false;
        }
    }, 350);
}

const {
    phoneInput,
    syncInput,
    onPhoneInput,
    onPhoneKeydown,
    clearPhone: resetPhoneMask,
} = usePhoneMaskInput(toRef(form, 'phone'), { onChange: refreshBiometricAvailability });

function clearPhone() {
    resetPhoneMask();
    biometricAvailable.value = false;
    phoneInput.value?.focus();
}

async function onBiometricLogin() {
    try {
        await loginWithBiometric(form.phone);
    } catch {
        // error shown via biometricError
    }
}

function onIinInput(event) {
    form.iin = event.target.value.replace(/\D/g, '').slice(0, 12);
    event.target.value = form.iin;
}

function setClientType(type) {
    form.client_type = type;
    edsStep.value = 'form';
    edsError.value = '';
    edsSession.value = null;
    form.bin = '';
    form.company_name = '';

    if (type !== 'individual') {
        form.iin = '';
    }
}

async function submitForm() {
    edsError.value = '';

    if (isLegalEntity.value) {
        return;
    }

    form.post(route('auth.phone.store'));
}

async function startLegalEntityEds() {
    edsBusy.value = true;

    try {
        const response = await fetch('/api/auth/legal-entity/eds/start', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({
                phone: form.phone,
            }),
        });

        const data = await response.json();

        if (! response.ok) {
            throw new Error(data.message || t('auth.edsError'));
        }

        edsSession.value = data;
        edsStep.value = 'sign';
    } catch (error) {
        edsError.value = error?.message || t('auth.edsError');
    } finally {
        edsBusy.value = false;
    }
}

async function signWithEds() {
    if (! edsSession.value?.challenge_base64) {
        return;
    }

    edsBusy.value = true;
    edsError.value = '';

    try {
        const cms = await signBase64Detached(edsSession.value.challenge_base64);

        const response = await fetch(`/api/auth/legal-entity/eds/${edsSession.value.login_code}/verify`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
            },
            body: JSON.stringify({ cms }),
        });

        const data = await response.json();

        if (! response.ok) {
            throw new Error(data.message || t('auth.edsError'));
        }

        if (data.redirect) {
            router.visit(data.redirect);
        }
    } catch (error) {
        edsError.value = error?.message || t('auth.edsError');
    } finally {
        edsBusy.value = false;
    }
}

function backToForm() {
    edsStep.value = 'form';
    edsError.value = '';
    edsSession.value = null;
}

onMounted(() => {
    const savedPhone = getSavedPhone();

    if (savedPhone) {
        form.phone = phoneFromStorage(savedPhone);
        syncInput();
        refreshBiometricAvailability();
    }
});

onUnmounted(() => {
    if (biometricCheckTimer) {
        clearTimeout(biometricCheckTimer);
    }
});
</script>

<template>
    <SeoHead />

    <div class="app-frame">
        <div class="app-shell page-enter flex min-h-dvh flex-col px-margin-page pb-36">
            <header class="flex items-center justify-between gap-3 pt-4" style="padding-top: calc(16px + var(--safe-top))">
                <AppLogo show-wordmark />
                <LocaleSwitcher compact code-only />
            </header>

            <div class="flex flex-1 flex-col justify-center py-stack-section">
            <div class="mb-stack-section text-center">
                <h1 class="text-headline-xl">
                    {{ isLegalEntity ? t('auth.headingLegal') : t('auth.heading') }}
                </h1>
                <p class="mt-3 text-body-sm text-text-muted">
                    {{ t('auth.subtitle') }}
                </p>
            </div>

            <form v-if="!showEdsStep" class="space-y-stack-element" @submit.prevent="submitForm">
                <div>
                    <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('auth.clientTypeLabel') }}</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            class="rounded-xl border px-3 py-3 text-sm font-semibold transition"
                            :class="form.client_type === 'individual'
                                ? 'border-accent bg-accent/15 text-accent'
                                : 'border-outline-variant bg-surface-container-low text-text-muted'"
                            @click="setClientType('individual')"
                        >
                            {{ t('auth.clientIndividual') }}
                        </button>
                        <button
                            type="button"
                            class="rounded-xl border px-3 py-3 text-sm font-semibold transition"
                            :class="form.client_type === 'legal_entity'
                                ? 'border-accent bg-accent/15 text-accent'
                                : 'border-outline-variant bg-surface-container-low text-text-muted'"
                            @click="setClientType('legal_entity')"
                        >
                            {{ t('auth.clientLegal') }}
                        </button>
                    </div>
                </div>

                <div v-if="isLegalEntity" class="warning-box text-center font-semibold">
                    {{ t('auth.legalEntityComingSoon') }}
                </div>

                <div v-if="!isLegalEntity">
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

                <template v-if="!isLegalEntity">
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

                <button type="submit" class="btn-primary" :disabled="!canSubmit || edsBusy">
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
                </template>
            </form>

            <div v-else class="space-y-stack-element">
                <div class="rounded-2xl border border-outline-variant bg-surface-container-low p-4">
                    <h2 class="text-lg font-semibold">{{ t('auth.edsTitle') }}</h2>
                    <p class="mt-2 text-sm text-text-muted">{{ t('auth.edsSubtitle') }}</p>
                    <p class="mt-3 text-sm text-text-dim">
                        {{ t('auth.edsHint') }}
                        <a
                            href="https://pki.gov.kz/ncalayer/"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="text-accent hover:underline"
                        >{{ t('auth.edsInstallLink') }}</a>.
                    </p>
                    <div class="mt-4 space-y-1 text-sm">
                        <p><span class="text-text-dim">{{ t('auth.phoneLabel') }}:</span> {{ form.phone }}</p>
                        <p class="text-xs text-text-dim">{{ t('auth.edsCertDataHint') }}</p>
                    </div>
                </div>

                <button type="button" class="btn-primary" :disabled="edsBusy" @click="signWithEds">
                    {{ edsBusy ? t('auth.edsSigning') : t('auth.edsSignButton') }}
                </button>

                <button type="button" class="btn-secondary w-full" :disabled="edsBusy" @click="backToForm">
                    {{ t('auth.edsBack') }}
                </button>

                <p v-if="edsError" class="text-sm text-error">{{ edsError }}</p>
            </div>

            <p class="mt-8 text-center text-body-sm text-text-dim">
                {{ t('auth.legalPrefix') }}
                <Link :href="route('legal.show', 'terms')" class="text-accent hover:underline">{{ t('auth.terms') }}</Link>,
                <Link :href="route('legal.show', 'privacy')" class="text-accent hover:underline">{{ t('auth.privacy') }}</Link>
                {{ t('auth.personalDataPrefix') }}
                <Link :href="route('legal.show', 'personal-data')" class="text-accent hover:underline">{{ t('auth.personalData') }}</Link>.
            </p>

            <p class="mt-stack-section text-center text-body-sm text-text-dim">
                Copyright © 2026 kztusdt.kz
            </p>
            </div>
        </div>
    </div>

    <PwaInstallPrompt />
</template>
