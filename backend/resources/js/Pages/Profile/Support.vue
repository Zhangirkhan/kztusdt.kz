<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    supportEmail: String,
    supportPhone: String,
    companyName: String,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('support.title')" />

    <ExchangeLayout>
        <template #title>{{ t('support.title') }}</template>

        <ProfileSettingsShell>
            <section class="card mb-4">
                <p class="text-label-caps uppercase text-text-dim">{{ t('support.contacts') }}</p>
                <p class="mt-3 text-body-sm text-text-muted">
                    {{ t('support.contactsHint', { companyName }) }}
                </p>

                <div class="mt-4 space-y-3">
                    <a v-if="supportEmail" :href="`mailto:${supportEmail}`" class="settings-item rounded-xl no-underline">
                        <span class="settings-item__icon"><span class="material-symbols-outlined">mail</span></span>
                        <span>
                            <span class="settings-item__label block">{{ t('support.email') }}</span>
                            <span class="text-sm text-accent">{{ supportEmail }}</span>
                        </span>
                    </a>
                    <a v-if="supportPhone" :href="`tel:${supportPhone}`" class="settings-item rounded-xl no-underline">
                        <span class="settings-item__icon"><span class="material-symbols-outlined">call</span></span>
                        <span>
                            <span class="settings-item__label block">{{ t('support.phone') }}</span>
                            <span class="text-sm text-accent">{{ supportPhone }}</span>
                        </span>
                    </a>
                </div>
            </section>

            <section class="info-box">
                <p class="font-semibold">{{ t('support.workingHours') }}</p>
                <p class="mt-2">{{ t('support.workingHoursDetail') }}</p>
            </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
