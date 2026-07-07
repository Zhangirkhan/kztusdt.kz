<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileHub from '@/widgets/profile-hub/ui/ProfileHub.vue';
import { useProfileLogout } from '@/features/profile-logout/model/useProfileLogout';
import { useProfileMenu } from '@/features/profile-menu/model/useProfileMenu';
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    profile: Object,
});

const page = usePage();
const { t } = useI18n();
const { menuItems, canUseWallet } = useProfileMenu();
const { logout } = useProfileLogout();

const userId = computed(() => page.props.auth?.user?.id ?? '—');
</script>

<template>
    <Head :title="t('profile.title')" />

    <ExchangeLayout>
        <template #title>{{ t('profile.title') }}</template>

        <ProfileHub
            :profile="profile"
            :user-id="userId"
            :menu-items="menuItems"
            :can-use-wallet="canUseWallet"
        />

        <button type="button" class="btn-secondary mt-6 block w-full text-center text-error" @click="logout">
            {{ t('profile.logout') }}
        </button>

        <p class="version-text">kztusdt.kz · OTC Exchange</p>
    </ExchangeLayout>
</template>
