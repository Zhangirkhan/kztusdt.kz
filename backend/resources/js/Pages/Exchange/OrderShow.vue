<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { formatKzt, formatPercent, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    order: Object,
    paymentRequest: Object,
});

const page = usePage();

const proofForm = useForm({ proof: null });

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ожидает оплату KZT',
    payment_proof_uploaded: 'Скрин загружен, ждёт проверки',
    pending_admin_confirmation: 'На подтверждении у администратора',
    kzt_received: 'KZT получены',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    dispute: 'Спор',
    manual_review: 'Ручная проверка',
};

const isBuy = computed(() => props.order.direction === 'buy');

const canUploadProof = computed(
    () => isBuy.value && ['awaiting_kzt_payment', 'payment_proof_uploaded'].includes(props.order.status),
);

const canCancel = computed(() =>
    isBuy.value
        ? ['awaiting_kzt_payment', 'payment_proof_uploaded', 'pending_admin_confirmation'].includes(props.order.status)
        : ['created', 'pending_admin_confirmation'].includes(props.order.status),
);

function onProofFile(event) {
    proofForm.proof = event.target.files[0] ?? null;
}

function uploadProof() {
    proofForm.post(route('exchange.orders.proof', props.order.id), { forceFormData: true });
}

function cancelOrder() {
    if (confirm('Отменить заявку?')) {
        router.post(route('exchange.orders.cancel', props.order.id));
    }
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}
</script>

<template>
    <Head :title="`Заявка №${order.id}`" />

    <ExchangeLayout>
        <template #title>Заявка №{{ order.id }}</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>

        <section class="card mb-stack-element">
            <div class="flex items-center justify-between">
                <p class="text-headline-md">{{ order.direction === 'buy' ? 'Покупка USDT' : 'Продажа USDT' }}</p>
                <span
                    class="rounded-lg px-3 py-1 text-xs font-semibold"
                    :class="order.status === 'completed' ? 'bg-accent/15 text-accent'
                        : ['cancelled', 'failed'].includes(order.status) ? 'bg-surface-container text-text-dim'
                        : 'bg-amber-500/15 text-amber-300'"
                >
                    {{ statusLabels[order.status] ?? order.status }}
                </span>
            </div>

            <div class="mt-4 space-y-1 text-body-sm">
                <div class="flex justify-between"><span class="text-text-dim">Сумма KZT</span><span>{{ formatKzt(order.fiat_amount) }} ₸</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Сумма USDT</span><span>{{ formatUsdt(order.crypto_amount, 4) }}</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Курс</span><span>{{ formatRate(order.rate) }} ₸/USDT</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Комиссия</span><span>{{ formatUsdt(order.fee_amount, 4) }} USDT ({{ formatPercent(order.fee_percent) }}%)</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Создана</span><span>{{ formatDate(order.created_at) }}</span></div>
                <div v-if="order.completed_at" class="flex justify-between"><span class="text-text-dim">Завершена</span><span>{{ formatDate(order.completed_at) }}</span></div>
                <div v-if="order.reject_reason" class="flex justify-between text-red-400"><span>Причина</span><span>{{ order.reject_reason }}</span></div>
            </div>
        </section>

        <!-- Buy: exchanger requisites + proof upload -->
        <section v-if="isBuy && paymentRequest && !['cancelled', 'failed', 'completed'].includes(order.status)" class="card mb-stack-element">
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">Реквизиты для оплаты</h2>
            <div class="space-y-1 text-body-sm">
                <div class="flex justify-between"><span class="text-text-dim">Банк</span><span class="font-semibold">{{ paymentRequest.bank_name }}</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Получатель</span><span class="font-semibold">{{ paymentRequest.recipient_name }}</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Счёт / карта</span><span class="font-semibold">{{ paymentRequest.recipient_account }}</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Сумма</span><span class="font-semibold text-accent">{{ formatKzt(paymentRequest.amount) }} ₸</span></div>
            </div>
            <p class="mt-3 text-xs text-amber-300">
                Переведите точную сумму и загрузите скриншот подтверждения. Укажите номер заявки №{{ order.id }} в комментарии к переводу.
            </p>

            <div v-if="canUploadProof" class="mt-4">
                <label class="card block cursor-pointer bg-surface-container-low">
                    <span class="text-sm">{{ paymentRequest.proof_file_path ? 'Заменить скрин оплаты' : 'Скриншот оплаты (JPG/PNG/PDF)' }}</span>
                    <input type="file" accept="image/*,.pdf" class="mt-2 block w-full text-sm" @change="onProofFile" />
                </label>
                <p v-if="proofForm.errors.proof" class="mt-2 text-sm text-red-400">{{ proofForm.errors.proof }}</p>
                <button class="btn-primary mt-3" :disabled="!proofForm.proof || proofForm.processing" @click="uploadProof">
                    Отправить скрин
                </button>
            </div>
            <p v-else-if="paymentRequest.proof_file_path" class="mt-3 text-body-sm text-accent">
                Скрин оплаты загружен, ожидайте подтверждения администратора.
            </p>
        </section>

        <!-- Sell: client bank details -->
        <section v-if="!isBuy && paymentRequest" class="card mb-stack-element">
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">KZT будут отправлены на</h2>
            <div class="space-y-1 text-body-sm">
                <div class="flex justify-between"><span class="text-text-dim">Банк</span><span>{{ paymentRequest.bank_name }}</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Получатель</span><span>{{ paymentRequest.recipient_name }}</span></div>
                <div class="flex justify-between"><span class="text-text-dim">Счёт / карта</span><span>{{ paymentRequest.recipient_account }}</span></div>
                <div v-if="paymentRequest.payment_reference" class="flex justify-between">
                    <span class="text-text-dim">Референс платежа</span><span class="font-semibold">{{ paymentRequest.payment_reference }}</span>
                </div>
            </div>
            <p v-if="order.status === 'pending_admin_confirmation'" class="mt-3 text-xs text-amber-300">
                USDT заблокированы. Администратор переведёт KZT и подтвердит заявку.
            </p>
        </section>

        <p v-if="page.props.errors?.form" class="mb-4 text-sm text-red-400">{{ page.props.errors.form }}</p>

        <button v-if="canCancel" class="w-full rounded-xl bg-surface-container py-3 text-sm font-semibold text-red-400" @click="cancelOrder">
            Отменить заявку
        </button>

        <Link href="/exchange" class="mt-4 block text-center text-body-sm text-text-dim">← К обмену</Link>
    </ExchangeLayout>
</template>
