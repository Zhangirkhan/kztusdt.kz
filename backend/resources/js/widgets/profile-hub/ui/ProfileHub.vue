<script setup>
import { kycGateMessage } from '@/entities/profile/model/menu';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
    canUseWallet: {
        type: Boolean,
        default: true,
    },
});

const { t } = useI18n();
const kycBannerMessage = computed(() => kycGateMessage(props.profile?.kyc_status));
const languageInsertIndex = 3;
const menuItemsBeforeLanguage = computed(() => props.menuItems.slice(0, languageInsertIndex));
const menuItemsAfterLanguage = computed(() => props.menuItems.slice(languageInsertIndex));
</script>

<template>
    <Link
        v-if="!canUseWallet"
        :href="route('kyc')"
        class="card mb-4 flex items-start gap-3 border border-accent/30 bg-primary-light/40 p-4 no-underline transition hover:bg-primary-light/60"
    >
        <span class="material-symbols-outlined shrink-0 text-xl text-accent">verified_user</span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-on-surface">{{ t('profile.kycRequired') }}</p>
            <p class="mt-1 text-sm text-text-muted">{{ kycBannerMessage }}</p>
        </div>
        <span class="material-symbols-outlined ml-auto shrink-0 text-text-dim">chevron_right</span>
    </Link>

    <div class="settings-list">
        <Link
            v-for="item in menuItemsBeforeLanguage"
            :key="item.href + item.label"
            :href="item.href"
            class="settings-item"
            :class="{ 'settings-item--locked': item.locked }"
            :aria-label="item.locked ? `${item.label} — ${t('nav.kycLockHint')}` : item.label"
        >
            <span class="settings-item__icon">
                <span class="material-symbols-outlined text-lg">{{ item.icon }}</span>
            </span>
            <span class="settings-item__label">{{ item.label }}</span>
            <span v-if="item.value" class="settings-item__value">{{ item.value }}</span>
            <span
                v-if="item.locked"
                class="material-symbols-outlined text-lg text-text-dim"
                aria-hidden="true"
            >
                lock
            </span>
            <span v-else class="material-symbols-outlined text-lg text-text-dim">chevron_right</span>
        </Link>

        <div class="settings-item settings-item--control">
            <span class="settings-item__icon">
                <span class="material-symbols-outlined text-lg">translate</span>
            </span>
            <span class="settings-item__label">{{ t('profile.menu.language') }}</span>
            <LocaleSwitcher class="locale-switcher--inline ml-auto shrink-0" compact code-only />
        </div>

        <Link
            v-for="item in menuItemsAfterLanguage"
            :key="item.href + item.label"
            :href="item.href"
            class="settings-item"
            :class="{ 'settings-item--locked': item.locked }"
            :aria-label="item.locked ? `${item.label} — ${t('nav.kycLockHint')}` : item.label"
        >
            <span class="settings-item__icon">
                <span class="material-symbols-outlined text-lg">{{ item.icon }}</span>
            </span>
            <span class="settings-item__label">{{ item.label }}</span>
            <span v-if="item.value" class="settings-item__value">{{ item.value }}</span>
            <span
                v-if="item.locked"
                class="material-symbols-outlined text-lg text-text-dim"
                aria-hidden="true"
            >
                lock
            </span>
            <span v-else class="material-symbols-outlined text-lg text-text-dim">chevron_right</span>
        </Link>
    </div>
</template>
