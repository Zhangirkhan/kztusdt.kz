<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import { formatPercent } from '@/shared/lib/format/number';
import { formatDate } from '@/shared/lib/format/date';
import {
    formatNational,
    getKzPhoneError,
    isKzPhoneComplete,
    MIN_PHONE,
    parseNationalDigits,
} from '@/utils/phoneMask';
import { usePhoneMaskInput } from '@/composables/usePhoneMaskInput';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, toRef } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: Object,
});

const page = usePage();
const { t, locale } = useI18n();

function initialPhone() {
    if (!props.profile.phone) {
        return MIN_PHONE;
    }

    const national = parseNationalDigits(props.profile.phone);

    return national.length > 0 ? formatNational(national) : MIN_PHONE;
}

const form = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    phone: initialPhone(),
});

const { phoneInput, onPhoneInput, onPhoneKeydown } = usePhoneMaskInput(toRef(form, 'phone'));

const phoneError = computed(() => getKzPhoneError(form.phone, t));
const canSubmit = computed(() => isKzPhoneComplete(form.phone) && form.name.trim() !== '' && !form.processing);

function submit() {
    form.patch(route('profile.update'), { preserveScroll: true });
}

function formatProfileDate(value) {
    return formatDate(value, locale.value);
}

const kycLabel = computed(() => t(`profile.kyc.${props.profile.kyc_status}`, props.profile.kyc_status));
</script>

<template>
    <Head title="Личные данные" />

    <ExchangeLayout>
        <template #title>Личные данные</template>

        <ProfileSettingsShell>
            <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />
        <section class="card mb-stack-element">
            <p class="text-label-caps uppercase text-text-dim">{{ t('profile.account') }}</p>
            <div class="mt-3 space-y-2 text-body-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-text-dim">KYC</span>
                    <span class="font-semibold capitalize text-accent">{{ kycLabel }}</span>
                </div>
            </div>
        </section>

        <section class="card mb-stack-element">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-label-caps uppercase text-text-dim">{{ t('profile.tariffs') }}</p>
                    <p class="mt-2 text-body-sm text-text-muted">{{ t('profile.tariffsHint') }}</p>
                </div>
                <span
                    class="rounded-lg px-3 py-1 text-xs font-semibold"
                    :class="profile.has_subscription ? 'bg-accent/20 text-accent' : 'bg-surface-container-high text-text-dim'"
                >
                    {{ profile.has_subscription ? profile.tariffs.subscription.name : profile.tariffs.standard.name }}
                </span>
            </div>

            <p v-if="profile.subscription?.expires_at" class="mt-3 text-sm text-accent">
                {{
                    t('profile.subscriptionActive', {
                        name: profile.tariffs.subscription.name,
                        date: formatProfileDate(profile.subscription.expires_at),
                        fee: formatPercent(profile.fee_percent),
                    })
                }}
            </p>
            <p v-else class="mt-3 text-sm text-on-surface">
                {{
                    t('profile.standardTariff', {
                        name: profile.tariffs.standard.name,
                        fee: formatPercent(profile.fee_percent),
                    })
                }}
            </p>
        </section>

        <form class="space-y-stack-element" @submit.prevent="submit">
            <section class="card space-y-4">
                <p class="text-label-caps uppercase text-text-dim">{{ t('profile.personalData') }}</p>

                <div>
                    <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('profile.name') }}</label>
                    <input v-model="form.name" class="input-field" required maxlength="255" autocomplete="name" />
                    <p v-if="form.errors.name" class="mt-2 text-sm text-error">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('profile.email') }}</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="input-field"
                        maxlength="255"
                        autocomplete="email"
                        placeholder="email@example.com"
                    />
                    <p v-if="form.errors.email" class="mt-2 text-sm text-error">{{ form.errors.email }}</p>
                    <p v-else class="mt-2 text-xs text-text-dim">{{ t('profile.emailHint') }}</p>
                </div>

                <div>
                    <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('profile.phone') }}</label>
                    <input
                        ref="phoneInput"
                        :value="form.phone"
                        type="tel"
                        class="input-field"
                        autocomplete="tel"
                        inputmode="numeric"
                        maxlength="18"
                        @input="onPhoneInput"
                        @keydown="onPhoneKeydown"
                    />
                    <p v-if="form.errors.phone" class="mt-2 text-sm text-error">{{ form.errors.phone }}</p>
                    <p v-else-if="phoneError" class="mt-2 text-sm text-error">{{ phoneError }}</p>
                    <p v-else-if="profile.phone_verified" class="mt-2 text-xs text-accent">{{ t('profile.phoneVerified') }}</p>
                    <p v-else class="mt-2 text-xs text-amber-400">
                        {{ t('profile.phoneNotVerified') }}
                        <Link :href="route('auth.phone')" class="font-semibold text-accent hover:underline">{{ t('profile.confirmPhone') }}</Link>
                    </p>
                    <p class="mt-1 text-xs text-text-dim">
                        {{ t('profile.phoneChangeHint') }}
                    </p>
                </div>
            </section>

            <button type="submit" class="btn-primary" :disabled="!canSubmit">
                {{ form.processing ? t('profile.saving') : t('profile.save') }}
            </button>
        </form>

        <section v-if="profile.kyc_first_name || profile.kyc_last_name" class="mt-stack-element card">
            <p class="text-label-caps uppercase text-text-dim">{{ t('profile.kycData') }}</p>
            <p class="mt-2 text-body-sm text-on-surface">
                {{ profile.kyc_first_name }} {{ profile.kyc_last_name }}
            </p>
            <p class="mt-1 text-xs text-text-dim">{{ t('profile.kycDataHint') }}</p>
            <Link :href="route('kyc')" class="mt-3 inline-block text-sm font-semibold text-accent">KYC →</Link>
        </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
