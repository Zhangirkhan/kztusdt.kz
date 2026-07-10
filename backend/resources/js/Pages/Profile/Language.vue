<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    profile: Object,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('profile.menu.language')" />

    <ExchangeLayout>
        <template #title>{{ t('profile.menu.language') }}</template>

        <ProfileSettingsShell>
            <LocaleSwitcher variant="list" />
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
