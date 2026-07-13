<script setup>
import SeoHead from '@/Components/SeoHead.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import { useBiometricAuth } from '@/composables/useBiometricAuth';
import { navigateAfterAuth } from '@/utils/authNavigation';
import { isAppLockConfigured } from '@/utils/appLockStorage';
import AppLockOverlay from '@/widgets/app-lock/ui/AppLockOverlay.vue';
import { localizedPath } from '@/utils/localizedPath';
import {
    clearRegistrationProgress,
    writeRegistrationProgress,
} from '@/utils/registrationProgress';

const SumsubKycWidget = defineAsyncComponent(() => import('@/Components/SumsubKycWidget.vue'));

const props = defineProps({
    loginCode: String,
    phone: String,
    status: String,
    expiresAt: String,
    codeLength: { type: Number, default: 6 },
    initialStep: { type: String, default: 'whatsapp' },
    kycStatus: { type: String, default: 'none' },
    kyc: { type: Object, default: null },
});

const { t, locale } = useI18n();
const {
    savePhone,
} = useBiometricAuth();

const step = ref(props.initialStep);
const kycStatus = ref(props.kycStatus);
const pendingRedirect = ref(null);

const code = ref('');
const codeInput = ref(null);
const submitting = ref(false);
const resending = ref(false);
const errorMessage = ref('');
const resendCooldown = ref(0);
let cooldownTimer = null;

const showInlineSumsub = ref(
    props.kyc?.inline_sumsub === true && !['approved', 'pending_review'].includes(kycStatus.value),
);

const isExpired = computed(() => props.status === 'expired');
const canResend = computed(() => !resending.value && resendCooldown.value <= 0);
const canSubmit = computed(() => code.value.length === props.codeLength && !submitting.value && !isExpired.value);
const expiresAtLabel = computed(() =>
    props.expiresAt
        ? new Date(props.expiresAt).toLocaleTimeString(
            locale.value === 'kk' ? 'kk-KZ' : locale.value === 'en' ? 'en-US' : 'ru-RU',
        )
        : '',
);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function onCodeInput(event) {
    code.value = event.target.value.replace(/\D/g, '').slice(0, props.codeLength);

    if (codeInput.value) {
        codeInput.value.value = code.value;
    }

    errorMessage.value = '';

    if (canSubmit.value) {
        submitCode();
    }
}

async function submitCode() {
    if (!canSubmit.value) {
        return;
    }

    submitting.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`/api/auth/phone/verify/${props.loginCode}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({ code: code.value }),
        });

        const result = await response.json();

        if (!response.ok) {
            errorMessage.value = result.message ?? t('auth.verify.errors.invalidCode');
            code.value = '';

            if (codeInput.value) {
                codeInput.value.value = '';
                codeInput.value.focus();
            }

            return;
        }

        savePhone(props.phone);

        if (result.user_id && !isAppLockConfigured(result.user_id)) {
            pendingRedirect.value = result;
            persistStep('app-lock');
            await router.reload({ preserveState: true });
            step.value = 'app-lock';

            return;
        }

        continueAfterLogin(result);
    } catch {
        errorMessage.value = t('auth.verify.errors.submitFailed');
    } finally {
        submitting.value = false;
    }
}

function startCooldown(seconds) {
    resendCooldown.value = seconds;

    if (cooldownTimer) {
        clearInterval(cooldownTimer);
    }

    cooldownTimer = setInterval(() => {
        resendCooldown.value -= 1;

        if (resendCooldown.value <= 0) {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
    }, 1000);
}

async function resendCode() {
    if (resending.value || resendCooldown.value > 0) {
        return;
    }

    resending.value = true;
    errorMessage.value = '';

    try {
        const response = await fetch(`/api/auth/phone/resend/${props.loginCode}`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
        });

        const result = await response.json();

        if (!response.ok) {
            errorMessage.value = result.message ?? t('auth.verify.errors.resendFailed');

            return;
        }

        startCooldown(60);

        if (result.login_code && result.login_code !== props.loginCode) {
            router.get(localizedPath(`/auth/whatsapp/${result.login_code}`), {}, { replace: true });
        } else {
            router.reload({ only: ['loginCode', 'phone', 'status', 'expiresAt', 'codeLength'] });
        }
    } catch {
        errorMessage.value = t('auth.verify.errors.resendFailed');
    } finally {
        resending.value = false;
    }
}

function persistStep(nextStep) {
    writeRegistrationProgress({
        loginCode: props.loginCode,
        phone: props.phone,
        step: nextStep,
    });
}

function finishRegistration(url = '/wallet') {
    clearRegistrationProgress();
    navigateAfterAuth(url);
}

function continueAfterLogin(result) {
    if (result.kyc?.needs_verification) {
        if (result.kyc?.inline_sumsub) {
            step.value = 'kyc';
            persistStep('kyc');
            kycStatus.value = result.kyc_status ?? kycStatus.value;
            showInlineSumsub.value = true;

            return;
        }

        clearRegistrationProgress();
        navigateAfterAuth('/kyc');

        return;
    }

    finishRegistration(result.redirect ?? '/wallet');
}

function onAppLockSetupComplete() {
    if (pendingRedirect.value) {
        continueAfterLogin(pendingRedirect.value);
    } else {
        finishRegistration('/wallet');
    }
}

function onKycApproved() {
    finishRegistration('/wallet');
}

function onKycPending() {
    kycStatus.value = 'pending_review';
    showInlineSumsub.value = false;
}

onMounted(() => {
    const progressStep = step.value === 'whatsapp' ? 'otp' : step.value === 'app-lock' ? 'app-lock' : 'kyc';
    persistStep(progressStep);

    if (step.value === 'whatsapp') {
        if (isExpired.value) {
            resendCooldown.value = 0;
        } else {
            startCooldown(60);
        }

        codeInput.value?.focus();
    }
});

onUnmounted(() => {
    if (cooldownTimer) {
        clearInterval(cooldownTimer);
    }
});
</script>

<template>
    <SeoHead />
    <Head :title="step === 'kyc' ? t('auth.verify.kyc.title') : step === 'app-lock' ? t('appLock.setup.createTitle') : t('auth.verify.heading')" />

    <AppLockOverlay v-if="step === 'app-lock'" mode="setup" @setup-complete="onAppLockSetupComplete" />

    <div v-else class="app-frame">
        <div class="app-shell page-enter flex min-h-dvh flex-col px-margin-page pb-8">
        <div class="flex flex-1 flex-col justify-center py-stack-section">
            <div v-if="step === 'whatsapp'" class="mb-stack-element">
                <div class="mb-6 flex items-center justify-center gap-3 text-xs font-semibold uppercase tracking-wide">
                    <span :class="step === 'whatsapp' ? 'text-accent' : 'text-text-dim'">{{ t('auth.verify.stepLabel') }}</span>
                    <span class="text-text-dim">→</span>
                    <span :class="step === 'kyc' ? 'text-accent' : 'text-text-dim'">{{ t('auth.verify.stepKyc') }}</span>
                </div>
            </div>

            <template v-if="step === 'whatsapp'">
                <div class="mb-stack-section text-center">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-primary-light">
                        <span class="material-symbols-outlined text-4xl text-accent">sms</span>
                    </div>
                    <h1 class="text-headline-xl">{{ t('auth.verify.heading') }}</h1>
                    <p class="mt-3 text-body-sm text-text-muted">
                        {{ t('auth.verify.subtitle', { phone }) }}
                    </p>
                    <p v-if="isExpired" class="mt-3 text-sm text-error">
                        {{ t('auth.verify.codeExpired') }}
                    </p>
                </div>

                <form class="space-y-stack-element" @submit.prevent="submitCode">
                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('auth.verify.codeLabel') }}</label>
                        <input
                            ref="codeInput"
                            :value="code"
                            type="text"
                            class="input-field text-center text-2xl tracking-[0.5em]"
                            :placeholder="'0'.repeat(codeLength)"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            :maxlength="codeLength"
                            @input="onCodeInput"
                        />
                        <p v-if="errorMessage" class="mt-2 text-sm text-error">{{ errorMessage }}</p>
                        <p v-else class="mt-2 text-xs text-text-dim">{{ t('auth.verify.noCode') }}</p>
                    </div>

                    <button type="submit" class="btn-primary" :disabled="!canSubmit">
                        {{ t('auth.verify.submit') }}
                    </button>
                </form>

                <div class="mt-6 flex flex-col items-center gap-2 text-body-sm">
                    <button
                        type="button"
                        class="text-accent hover:underline disabled:text-text-dim disabled:no-underline"
                        :disabled="!canResend"
                        @click="resendCode"
                    >
                        {{ resendCooldown > 0 ? t('auth.verify.resendIn', { seconds: resendCooldown }) : t('auth.verify.resend') }}
                    </button>
                    <Link
                        :href="route('auth.phone')"
                        class="text-text-dim hover:underline"
                        @click="clearRegistrationProgress"
                    >{{ t('auth.verify.wrongNumber') }}</Link>
                    <p v-if="expiresAtLabel" class="text-text-dim">
                        {{ t('auth.verify.expiresAt', { time: expiresAtLabel }) }}
                    </p>
                </div>
            </template>

            <template v-else>
                <div class="mb-stack-section text-center">
                    <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-primary-light">
                        <span class="material-symbols-outlined text-4xl text-accent">verified_user</span>
                    </div>
                    <h1 class="text-headline-xl">{{ t('auth.verify.kyc.title') }}</h1>
                    <p class="mt-3 text-body-sm text-text-muted">
                        {{ t('auth.verify.kyc.subtitle') }}
                    </p>
                </div>

                <section v-if="showInlineSumsub" class="card">
                    <SumsubKycWidget
                        container-id="onboarding-sumsub"
                        :kyc-status="kycStatus"
                        @approved="onKycApproved"
                        @pending="onKycPending"
                    />
                </section>

                <section v-else-if="kycStatus === 'pending_review'" class="card text-center">
                    <p class="font-semibold text-accent">{{ t('auth.verify.kyc.pendingTitle') }}</p>
                    <p class="mt-2 text-body-sm text-text-muted">{{ t('auth.verify.kyc.pendingHint') }}</p>
                    <Link :href="route('wallet')" class="btn-primary mt-4 inline-block text-center no-underline">{{ t('auth.verify.kyc.toHome') }}</Link>
                </section>
            </template>
        </div>
        </div>
    </div>
</template>
