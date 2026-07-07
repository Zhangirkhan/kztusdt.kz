<script setup>
import { formatPercent } from '@/shared/lib/format/number';
import { formatDate } from '@/shared/lib/format/date';
import { isProfileKycVerified, kycGateMessage } from '@/entities/profile/model/menu';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: {
        type: Object,
        required: true,
    },
    userId: {
        type: [String, Number],
        default: '—',
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

const { locale } = useI18n();
const kycVerified = computed(() => isProfileKycVerified(props.profile));
const kycBannerMessage = computed(() => kycGateMessage(props.profile?.kyc_status));
</script>

<template>
    <Link
        v-if="!canUseWallet"
        :href="route('kyc')"
        class="card mb-4 flex items-start gap-3 border border-accent/30 bg-primary-light/40 p-4 no-underline transition hover:bg-primary-light/60"
    >
        <span class="material-symbols-outlined shrink-0 text-xl text-accent">verified_user</span>
        <div class="min-w-0">
            <p class="text-sm font-semibold text-on-surface">Требуется KYC</p>
            <p class="mt-1 text-sm text-text-muted">{{ kycBannerMessage }}</p>
        </div>
        <span class="material-symbols-outlined ml-auto shrink-0 text-text-dim">chevron_right</span>
    </Link>

    <Link :href="route('profile.personal')" class="profile-card profile-card--clickable mb-4 no-underline">
        <div class="profile-card__avatar">
            <span class="material-symbols-outlined text-3xl">person</span>
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <span class="truncate text-lg font-semibold text-on-surface">{{ profile.name }}</span>
                <span v-if="kycVerified" class="verified-badge">Verified</span>
            </div>
            <div class="text-sm text-text-muted">{{ profile.phone }}</div>
            <div class="mt-1 flex items-center gap-2 text-sm text-text-muted">ID: {{ userId }}</div>
            <p v-if="profile.has_subscription" class="mt-2 text-xs text-accent">
                {{ profile.tariffs.subscription.name }} · до {{ formatDate(profile.subscription?.expires_at, locale) }}
            </p>
            <p v-else class="mt-2 text-xs text-text-dim">
                {{ profile.tariffs.standard.name }} · комиссия {{ formatPercent(profile.fee_percent) }}%
            </p>
        </div>
        <span class="profile-card__chevron material-symbols-outlined">chevron_right</span>
    </Link>

    <div class="settings-list">
        <Link
            v-for="item in menuItems"
            :key="item.href + item.label"
            :href="item.href"
            class="settings-item"
            :class="{ 'settings-item--locked': item.locked }"
            :aria-label="item.locked ? `${item.label} — пройдите KYC` : item.label"
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
