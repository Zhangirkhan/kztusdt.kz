<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
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
const { t } = useI18n();

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
</script>

<template>
    <Head title="Личные данные" />

    <ExchangeLayout>
        <template #title>Личные данные</template>

        <ProfileSettingsShell>
            <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />

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
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
