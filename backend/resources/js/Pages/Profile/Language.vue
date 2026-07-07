<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    profile: Object,
});
</script>

<template>
    <Head title="Сменить язык" />

    <ExchangeLayout>
        <template #title>Сменить язык</template>

        <ProfileSettingsShell>
            <section class="card">
                <p class="mb-4 text-label-caps uppercase text-text-dim">Язык интерфейса</p>
                <LocaleSwitcher show-label />
            </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
