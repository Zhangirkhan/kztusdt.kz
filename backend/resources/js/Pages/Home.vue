<script setup>
import ServiceHero from '@/Components/ServiceHero.vue';
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { formatKzt, formatPercent } from '@/utils/formatNumber';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, defineAsyncComponent, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const SumsubKycWidget = defineAsyncComponent(() => import('@/Components/SumsubKycWidget.vue'));
const KycIinMismatchForm = defineAsyncComponent(() => import('@/features/kyc-iin-mismatch/ui/KycIinMismatchForm.vue'));

const props = defineProps({
    companyHero: Object,
    userStatus: Object,
    rates: Object,
});
const { t } = useI18n();
const showIinMismatch = ref(props.userStatus?.iin_mismatch === true);

function onKycApproved(data) {
    if (data?.iin_mismatch) {
        showIinMismatch.value = true;

        return;
    }

    router.reload({ only: ['userStatus'] });
}

function onIinConfirmed() {
    showIinMismatch.value = false;
    router.reload({ only: ['userStatus'] });
}

const showInlineSumsub = computed(() =>
    !showIinMismatch.value && props.userStatus?.inline_sumsub === true,
);
</script>

<template>
    <Head :title="t('home.title')" />

    <ExchangeLayout>
        <template #title>{{ t('home.title') }}</template>

        <ServiceHero :company="companyHero" />

        <section class="card card--highlight mb-stack-element overflow-hidden">
            <p class="text-label-caps uppercase text-white/70">{{ t('home.rateTitle') }}</p>
            <p class="mt-2 text-3xl font-bold tracking-tight">{{ formatKzt(rates.usdt_kzt) }} ₸</p>
            <p class="mt-1 text-body-sm text-white/80">{{ t('home.fee', { percent: formatPercent(userStatus.fee_percent) }) }}</p>
        </section>

        <section v-if="!userStatus.phone_verified" class="warning-box">
            <p class="font-semibold">{{ t('home.phoneNotVerified') }}</p>
            <Link :href="route('auth.phone')" class="mt-3 inline-block font-semibold text-accent">{{ t('home.confirmPhone') }}</Link>
        </section>

        <KycIinMismatchForm
            v-else-if="showIinMismatch"
            class="mb-stack-element"
            @confirmed="onIinConfirmed"
        />

        <section v-else-if="showInlineSumsub" class="card">
            <p class="font-semibold">{{ t('home.kycTitle') }}</p>
            <p class="mt-2 text-body-sm text-text-muted">
                {{ t('home.kycInlineHint') }}
            </p>
            <div class="mt-4">
                <SumsubKycWidget
                    container-id="home-sumsub"
                    :kyc-status="userStatus.kyc_status"
                    compact
                    @approved="onKycApproved"
                />
            </div>
        </section>

        <section v-else-if="userStatus.kyc_status !== 'approved'" class="card">
            <p class="font-semibold">KYC: {{ userStatus.kyc_status }}</p>
            <p class="mt-2 text-body-sm text-text-muted">{{ t('home.kycRequiredHint') }}</p>
            <Link :href="route('kyc')" class="btn-primary mt-4 inline-block text-center no-underline">{{ t('home.completeKyc') }}</Link>
        </section>

        <section v-else class="grid grid-cols-2 gap-3">
            <Link :href="route('exchange')" class="card flex flex-col items-center text-center no-underline transition-transform active:scale-[0.98]">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-light text-accent">
                    <span class="material-symbols-outlined text-2xl">currency_exchange</span>
                </span>
                <p class="mt-3 text-sm font-semibold text-on-surface">{{ t('home.actions.exchange') }}</p>
            </Link>
            <Link :href="route('wallet')" class="card flex flex-col items-center text-center no-underline transition-transform active:scale-[0.98]">
                <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary-light text-accent">
                    <span class="material-symbols-outlined text-2xl">account_balance_wallet</span>
                </span>
                <p class="mt-3 text-sm font-semibold text-on-surface">{{ t('home.actions.wallet') }}</p>
            </Link>
        </section>

        <section v-if="$page.props.auth.isStaff" class="mt-stack-section card">
            <a
                :href="$page.props.adminNav?.landing ?? $page.props.adminApp?.url + '/admin'"
                class="inline-block text-sm font-semibold text-accent"
            >
                {{ t('home.adminPanel') }}
            </a>
        </section>
    </ExchangeLayout>
</template>
