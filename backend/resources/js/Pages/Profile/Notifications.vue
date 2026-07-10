<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import ToggleSwitch from '@/shared/ui/toggle-switch/ToggleSwitch.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: Object,
});

const page = usePage();
const { t } = useI18n();

const prefs = props.profile.notification_preferences ?? { push: true };
const form = useForm({
    push: Boolean(prefs.push),
});

function submit() {
    form.patch(route('profile.notifications.update'), { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('notifications.title')" />

    <ExchangeLayout>
        <template #title>{{ t('notifications.title') }}</template>

        <ProfileSettingsShell>
            <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />

            <p class="mb-4 text-sm text-text-muted">{{ t('notifications.hint') }}</p>

            <div class="settings-list">
                <div class="settings-item">
                    <span class="settings-item__icon">
                        <span class="material-symbols-outlined">notifications</span>
                    </span>
                    <span class="settings-item__label">{{ t('notifications.push') }}</span>
                    <ToggleSwitch v-model="form.push" :label="t('notifications.push')" @update:model-value="submit" />
                </div>
            </div>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
