<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import ToggleSwitch from '@/shared/ui/toggle-switch/ToggleSwitch.vue';
import { useTheme } from '@/composables/useTheme';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const { isDark, setDark } = useTheme();
</script>

<template>
    <Head :title="t('appearance.title')" />

    <ExchangeLayout>
        <template #title>{{ t('appearance.title') }}</template>

        <ProfileSettingsShell>
            <p class="mb-4 text-sm text-text-muted">{{ t('appearance.hint') }}</p>

            <div class="settings-list">
                <div class="settings-item">
                    <span class="settings-item__icon">
                        <span class="material-symbols-outlined">dark_mode</span>
                    </span>
                    <span class="settings-item__label">{{ t('appearance.darkMode') }}</span>
                    <ToggleSwitch :model-value="isDark" :label="t('appearance.darkMode')" @update:model-value="setDark" />
                </div>
            </div>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
