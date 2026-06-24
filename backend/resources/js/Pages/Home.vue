<script setup>
import ServiceHero from '@/Components/ServiceHero.vue';
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { formatKzt, formatPercent } from '@/utils/formatNumber';
import { Head, Link, router } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';

const SumsubKycWidget = defineAsyncComponent(() => import('@/Components/SumsubKycWidget.vue'));

defineProps({
    companyHero: Object,
    userStatus: Object,
    rates: Object,
});

function onKycApproved() {
    router.reload({ only: ['userStatus'] });
}
</script>

<template>
    <Head title="Главная" />

    <ExchangeLayout>
        <template #title>Главная</template>

        <ServiceHero :company="companyHero" />

        <section class="card mb-stack-element overflow-hidden">
            <p class="text-label-caps uppercase text-text-dim">Курс USDT / KZT</p>
            <p class="mt-2 text-3xl font-bold text-accent">{{ formatKzt(rates.usdt_kzt) }} ₸</p>
            <p class="mt-1 text-body-sm text-text-dim">Комиссия: {{ formatPercent(userStatus.fee_percent) }}%</p>
        </section>

        <section v-if="!userStatus.phone_verified" class="card border border-error/30">
            <p class="font-semibold text-error">Телефон не подтверждён</p>
            <Link href="/auth/phone" class="mt-3 inline-block text-accent">Подтвердить →</Link>
        </section>

        <section v-else-if="userStatus.inline_sumsub" class="card">
            <p class="font-semibold">Верификация личности</p>
            <p class="mt-2 text-body-sm text-text-muted">
                Сфотографируйте удостоверение и пройдите видео-проверку, чтобы получить кошелёк USDT
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
            <p class="mt-2 text-body-sm text-text-muted">Пройдите верификацию, чтобы получить кошелёк USDT</p>
            <Link href="/kyc" class="btn-primary mt-4 inline-block text-center no-underline">Пройти KYC</Link>
        </section>

        <section v-else class="grid grid-cols-2 gap-3">
            <Link href="/exchange" class="card text-center no-underline">
                <span class="material-symbols-outlined text-accent">currency_exchange</span>
                <p class="mt-2 text-sm font-semibold text-on-surface">Обмен</p>
            </Link>
            <Link href="/wallet" class="card text-center no-underline">
                <span class="material-symbols-outlined text-accent">account_balance_wallet</span>
                <p class="mt-2 text-sm font-semibold text-on-surface">Кошелёк</p>
            </Link>
        </section>

        <section v-if="$page.props.auth.isStaff" class="mt-stack-section card">
            <Link :href="$page.props.adminNav?.landing ?? '/admin'" class="inline-block text-sm font-semibold text-accent">
                Админ-панель →
            </Link>
        </section>
    </ExchangeLayout>
</template>
