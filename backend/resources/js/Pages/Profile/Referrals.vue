<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import { formatDateTime } from '@/shared/lib/format/date';
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps({
    referral: Object,
});

const { t } = useI18n();
const copied = ref(false);

async function copyLink(link) {
    try {
        await navigator.clipboard.writeText(link);
        copied.value = true;
        setTimeout(() => {
            copied.value = false;
        }, 2000);
    } catch {
        // clipboard may be unavailable
    }
}
</script>

<template>
    <Head :title="t('profile.referrals.title')" />

    <ExchangeLayout>
        <template #title>{{ t('profile.referrals.title') }}</template>

        <ProfileSettingsShell>
            <section class="card mb-4">
                <p class="text-label-caps uppercase text-text-dim">{{ t('profile.referrals.inviteLink') }}</p>
                <p class="mt-2 text-body-sm text-text-muted">{{ t('profile.referrals.inviteHint') }}</p>

                <p class="referral-link-field__text mt-4">{{ referral.link }}</p>

                <button type="button" class="btn-primary wallet-copy-cta" @click="copyLink(referral.link)">
                    <span class="material-symbols-outlined text-[20px]">
                        {{ copied ? 'check' : 'content_copy' }}
                    </span>
                    {{ copied ? t('profile.referrals.copied') : t('profile.referrals.copy') }}
                </button>

                <p class="mt-3 text-sm text-text-dim">
                    {{ t('profile.referrals.codeLabel') }}: <span class="font-mono text-text">{{ referral.code }}</span>
                </p>
            </section>

            <section v-if="referral.active_benefit" class="info-box mb-4">
                <p class="font-semibold">{{ t('profile.referrals.activeBenefitTitle') }}</p>
                <p v-if="referral.active_benefit.value" class="mt-2">
                    {{ t('profile.referrals.activeBenefitFee', { value: referral.active_benefit.value }) }}
                </p>
                <p v-if="referral.active_benefit.note" class="mt-2 text-sm text-text-muted">
                    {{ referral.active_benefit.note }}
                </p>
            </section>

            <section class="card">
                <div class="flex items-center justify-between gap-3">
                    <p class="text-label-caps uppercase text-text-dim">{{ t('profile.referrals.listTitle') }}</p>
                    <span class="text-sm text-text-muted">{{ t('profile.referrals.count', { count: referral.referrals_count }) }}</span>
                </div>

                <div v-if="referral.referrals.length === 0" class="mt-4 text-sm text-text-muted">
                    {{ t('profile.referrals.empty') }}
                </div>

                <ul v-else class="mt-4 space-y-3">
                    <li
                        v-for="item in referral.referrals"
                        :key="item.id"
                        class="rounded-xl border border-border-subtle px-4 py-3"
                    >
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <p class="font-medium text-text">{{ item.name }}</p>
                                <p v-if="item.phone_masked" class="text-sm text-text-muted">{{ item.phone_masked }}</p>
                            </div>
                            <span class="text-xs text-text-dim">{{ formatDateTime(item.registered_at) }}</span>
                        </div>
                        <div class="mt-2 flex flex-wrap gap-3 text-xs text-text-muted">
                            <span>{{ t('profile.referrals.kyc') }}: {{ t(`profile.kyc.${item.kyc_status}`, item.kyc_status) }}</span>
                            <span>{{ t('profile.referrals.orders', { count: item.orders_count }) }}</span>
                            <span>{{ t('profile.referrals.deposits', { count: item.deposits_count }) }}</span>
                        </div>
                    </li>
                </ul>
            </section>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>

<style scoped>
.referral-link-field__text {
    @apply break-all rounded-xl border p-4 font-mono text-sm leading-relaxed;
    border-color: var(--color-border);
    background: var(--color-surface-muted);
    color: var(--color-text);
}
</style>
