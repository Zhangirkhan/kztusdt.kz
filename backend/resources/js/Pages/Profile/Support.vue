<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    supportEmail: String,
    supportPhone: String,
    companyName: String,
});
</script>

<template>
    <Head title="Служба поддержки" />

    <ExchangeLayout>
        <template #title>Служба поддержки</template>

        <ProfileSettingsShell>
            <section class="card mb-4">
                <p class="text-label-caps uppercase text-text-dim">Контакты</p>
                <p class="mt-3 text-body-sm text-text-muted">
                    Служба поддержки {{ companyName }} поможет с KYC, обменом и выводом средств.
                </p>

                <div class="mt-4 space-y-3">
                    <a v-if="supportEmail" :href="`mailto:${supportEmail}`" class="settings-item rounded-xl no-underline">
                        <span class="settings-item__icon"><span class="material-symbols-outlined">mail</span></span>
                        <span>
                            <span class="settings-item__label block">Email</span>
                            <span class="text-sm text-accent">{{ supportEmail }}</span>
                        </span>
                    </a>
                    <a v-if="supportPhone" :href="`tel:${supportPhone}`" class="settings-item rounded-xl no-underline">
                        <span class="settings-item__icon"><span class="material-symbols-outlined">call</span></span>
                        <span>
                            <span class="settings-item__label block">Телефон</span>
                            <span class="text-sm text-accent">{{ supportPhone }}</span>
                        </span>
                    </a>
                </div>
            </section>

            <section class="info-box">
                <p class="font-semibold">Часы работы</p>
                <p class="mt-2">Пн–Пт, 10:00–19:00 (Asia/Almaty). Ответ обычно в течение рабочего дня.</p>
            </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
