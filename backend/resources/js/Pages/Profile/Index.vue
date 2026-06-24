<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { formatPercent } from '@/utils/formatNumber';
import {
    formatNational,
    getKzPhoneError,
    isKzPhoneComplete,
    MIN_PHONE,
    parseNationalDigits,
    updatePhoneMask,
} from '@/utils/phoneMask';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: Object,
    telegramBotUsername: String,
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

const phoneInput = ref(null);
const phoneError = computed(() => getKzPhoneError(form.phone, t));
const canSubmit = computed(() => isKzPhoneComplete(form.phone) && form.name.trim() !== '' && !form.processing);

function syncInput() {
    if (phoneInput.value) {
        phoneInput.value.value = form.phone;
    }
}

function onPhoneInput(event) {
    form.phone = updatePhoneMask(form.phone, event.target.value);
    syncInput();
}

function onPhoneKeydown(event) {
    if (event.key !== 'Backspace' && event.key !== 'Delete') {
        return;
    }

    const input = event.target;
    const { selectionStart, selectionEnd, value } = input;

    if (selectionStart === null || selectionEnd === null || selectionStart !== selectionEnd) {
        return;
    }

    if (event.key === 'Backspace' && selectionStart > 0 && /\D/.test(value[selectionStart - 1])) {
        event.preventDefault();
        form.phone = updatePhoneMask(form.phone, value.slice(0, selectionStart - 1) + value.slice(selectionStart));
        syncInput();
    }
}

function submit() {
    form.patch(route('profile.update'), { preserveScroll: true });
}

function formatDate(value) {
    if (!value) {
        return '';
    }

    const localeMap = {
        ru: 'ru-RU',
        kk: 'kk-KZ',
        en: 'en-US',
    };

    return new Date(value).toLocaleDateString(localeMap[locale.value] ?? 'ru-RU');
}

const kycLabel = computed(() => t(`profile.kyc.${props.profile.kyc_status}`, props.profile.kyc_status));
</script>

<template>
    <Head :title="t('profile.title')" />

    <ExchangeLayout>
        <template #title>{{ t('profile.title') }}</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>

        <section class="card mb-stack-element">
            <p class="text-label-caps uppercase text-text-dim">{{ t('profile.account') }}</p>
            <div class="mt-3 space-y-2 text-body-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-text-dim">KYC</span>
                    <span class="font-semibold capitalize text-accent">{{ kycLabel }}</span>
                </div>
                <div v-if="profile.telegram_username" class="flex justify-between gap-3">
                    <span class="text-text-dim">Telegram</span>
                    <span class="font-semibold">@{{ profile.telegram_username }}</span>
                </div>
            </div>
        </section>

        <section class="card mb-stack-element">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="text-label-caps uppercase text-text-dim">{{ t('profile.tariffs') }}</p>
                    <p class="mt-2 text-body-sm text-text-muted">
                        {{ t('profile.tariffsHint') }}
                    </p>
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
                        date: formatDate(profile.subscription.expires_at),
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

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                <article
                    class="rounded-xl border p-4"
                    :class="profile.has_subscription
                        ? 'border-accent/50 bg-accent/10'
                        : 'border-outline-variant/40 bg-surface-container-low'"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-on-surface">{{ profile.tariffs.subscription.name }}</h3>
                        <span v-if="profile.has_subscription" class="text-xs font-semibold text-accent">{{ t('profile.yourTariff') }}</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-accent">{{ formatPercent(profile.tariffs.subscription.fee_percent) }}%</p>
                    <p class="mt-1 text-xs text-text-dim">{{ profile.tariffs.subscription.timing }}</p>
                    <p class="mt-3 text-body-sm text-text-muted">{{ profile.tariffs.subscription.description }}</p>
                </article>

                <article
                    class="rounded-xl border p-4"
                    :class="!profile.has_subscription
                        ? 'border-accent/50 bg-accent/10'
                        : 'border-outline-variant/40 bg-surface-container-low'"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-semibold text-on-surface">{{ profile.tariffs.standard.name }}</h3>
                        <span v-if="!profile.has_subscription" class="text-xs font-semibold text-accent">{{ t('profile.yourTariff') }}</span>
                    </div>
                    <p class="mt-2 text-2xl font-bold text-on-surface">{{ formatPercent(profile.tariffs.standard.fee_percent) }}%</p>
                    <p class="mt-1 text-xs text-text-dim">{{ profile.tariffs.standard.timing }}</p>
                    <p class="mt-3 text-body-sm text-text-muted">{{ profile.tariffs.standard.description }}</p>
                </article>
            </div>

            <p v-if="!profile.has_subscription" class="mt-4 text-xs text-text-dim">
                {{
                    t('profile.subscriptionUpgrade', {
                        name: profile.tariffs.subscription.name,
                        fee: formatPercent(profile.tariffs.subscription.fee_percent),
                    })
                }}
                <a
                    v-if="profile.support_email"
                    :href="`mailto:${profile.support_email}`"
                    class="text-accent hover:underline"
                >{{ profile.support_email }}</a><template v-else>{{ t('profile.viaTelegramBot') }}</template>.
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
                        <Link href="/auth/phone" class="font-semibold text-accent hover:underline">{{ t('profile.confirmPhone') }}</Link>
                    </p>
                    <p class="mt-1 text-xs text-text-dim">
                        {{ t('profile.phoneChangeHint', { bot: `@${telegramBotUsername}` }) }}
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
            <Link href="/kyc" class="mt-3 inline-block text-sm font-semibold text-accent">KYC →</Link>
        </section>

        <section class="mt-stack-element card">
            <p class="mb-3 text-label-caps uppercase text-text-dim">{{ t('profile.language') }}</p>
            <LocaleSwitcher show-label />
        </section>

        <section class="mt-stack-element card">
            <Link href="/auth/phone" class="btn-secondary mb-3 block text-center no-underline">
                {{ t('profile.confirmPhoneTelegram') }}
            </Link>
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="btn-secondary block w-full text-center text-red-400 no-underline"
            >
                {{ t('profile.logout') }}
            </Link>
        </section>
    </ExchangeLayout>
</template>
