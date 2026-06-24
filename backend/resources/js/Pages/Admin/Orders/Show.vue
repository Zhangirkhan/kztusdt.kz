<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { formatKzt, formatPercent, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    order: Object,
    paymentRequest: Object,
});

const page = usePage();
const showReject = ref(false);

const confirmForm = useForm({ comment: '' });
const payoutForm = useForm({ payment_reference: '', bank_name: '', comment: '' });
const rejectForm = useForm({ reason: '' });

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ждёт оплату KZT',
    payment_proof_uploaded: 'Скрин загружен',
    pending_admin_confirmation: 'Ждёт подтверждения',
    kzt_received: 'KZT получены',
    completed: 'Выполнена',
    cancelled: 'Отменена',
    failed: 'Ошибка',
    dispute: 'Спор',
    manual_review: 'Ручная проверка',
};

const isBuy = computed(() => props.order.direction === 'buy');

const canConfirmBuy = computed(
    () => isBuy.value
        && ['awaiting_kzt_payment', 'payment_proof_uploaded', 'pending_admin_confirmation'].includes(props.order.status),
);

const canPayoutSell = computed(
    () => !isBuy.value && props.order.status === 'pending_admin_confirmation',
);

const canReject = computed(() => canConfirmBuy.value || canPayoutSell.value);

function confirmBuy() {
    if (confirm(`Подтвердить получение ${formatKzt(props.order.fiat_amount)} ₸ и зачислить USDT?`)) {
        confirmForm.post(`/admin/orders/${props.order.id}/confirm-payment`);
    }
}

function payoutSell() {
    if (confirm(`Подтвердить, что ${formatKzt(props.order.fiat_amount)} ₸ отправлены клиенту? USDT будут списаны окончательно.`)) {
        payoutForm.post(`/admin/orders/${props.order.id}/mark-kzt-sent`);
    }
}

function reject() {
    rejectForm.post(`/admin/orders/${props.order.id}/reject`);
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}
</script>

<template>
    <Head :title="`Заявка №${order.id}`" />

    <AdminLayout>
        <template #title>Заявка №{{ order.id }} — {{ order.direction === 'buy' ? 'покупка' : 'продажа' }}</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>
        <p v-if="page.props.errors?.form" class="mb-4 text-sm text-red-400">{{ page.props.errors.form }}</p>

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="card">
                <h2 class="mb-3 text-label-caps uppercase text-text-dim">Детали заявки</h2>
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-text-dim">Статус</span><span class="font-semibold text-amber-400">{{ statusLabels[order.status] ?? order.status }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Клиент</span><span>{{ order.user?.name }} · {{ order.user?.phone }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">KYC</span><span>{{ order.user?.kyc_status }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">KZT</span><span class="font-semibold">{{ formatKzt(order.fiat_amount) }} ₸</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">USDT</span><span class="font-semibold">{{ formatUsdt(order.crypto_amount, 4) }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Курс</span><span>{{ formatRate(order.rate) }} ₸/USDT</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Комиссия</span><span>{{ formatUsdt(order.fee_amount, 4) }} USDT ({{ formatPercent(order.fee_percent) }}%)</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Создана</span><span>{{ formatDate(order.created_at) }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Завершена</span><span>{{ formatDate(order.completed_at) }}</span></div>
                    <div v-if="order.confirmed_by" class="flex justify-between"><span class="text-text-dim">Подтвердил</span><span>{{ order.confirmed_by?.name ?? order.confirmed_by }}</span></div>
                    <div v-if="order.reject_reason" class="flex justify-between text-red-400"><span>Причина отмены</span><span>{{ order.reject_reason }}</span></div>
                </div>
            </section>

            <section class="card">
                <h2 class="mb-3 text-label-caps uppercase text-text-dim">
                    {{ isBuy ? 'Оплата клиента (KZT → обменник)' : 'Выплата клиенту (обменник → KZT)' }}
                </h2>
                <div v-if="paymentRequest" class="space-y-1 text-sm">
                    <div class="flex justify-between"><span class="text-text-dim">Банк</span><span>{{ paymentRequest.bank_name }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Получатель</span><span>{{ paymentRequest.recipient_name }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Счёт / карта</span><span class="font-mono">{{ paymentRequest.recipient_account }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Сумма</span><span class="font-semibold">{{ formatKzt(paymentRequest.amount) }} ₸</span></div>
                    <div v-if="paymentRequest.payment_reference" class="flex justify-between"><span class="text-text-dim">Референс</span><span>{{ paymentRequest.payment_reference }}</span></div>
                    <div class="flex justify-between"><span class="text-text-dim">Статус оплаты</span><span>{{ paymentRequest.status }}</span></div>
                </div>

                <div v-if="isBuy" class="mt-4">
                    <a
                        v-if="paymentRequest?.proof_file_path"
                        :href="`/admin/orders/${order.id}/proof`"
                        target="_blank"
                        class="inline-block rounded-xl bg-surface-container-high px-4 py-2 text-sm font-semibold text-accent"
                    >
                        📎 Открыть скрин оплаты
                    </a>
                    <p v-else class="text-sm text-text-dim">Скрин оплаты ещё не загружен.</p>
                </div>
            </section>
        </div>

        <!-- Actions -->
        <section v-if="canConfirmBuy || canPayoutSell || canReject" class="card mt-6">
            <h2 class="mb-3 text-label-caps uppercase text-text-dim">Действия</h2>

            <div v-if="canConfirmBuy" class="mb-4 space-y-2">
                <input v-model="confirmForm.comment" class="input-field" placeholder="Комментарий (необязательно)" />
                <button class="btn-primary" :disabled="confirmForm.processing" @click="confirmBuy">
                    ✅ Подтвердить оплату и зачислить USDT
                </button>
            </div>

            <div v-if="canPayoutSell" class="mb-4 space-y-2">
                <input v-model="payoutForm.payment_reference" class="input-field" placeholder="Референс / номер перевода *" />
                <p v-if="payoutForm.errors.payment_reference" class="text-sm text-red-400">{{ payoutForm.errors.payment_reference }}</p>
                <input v-model="payoutForm.bank_name" class="input-field" placeholder="Банк (если отличается)" />
                <input v-model="payoutForm.comment" class="input-field" placeholder="Комментарий (необязательно)" />
                <button class="btn-primary" :disabled="payoutForm.processing" @click="payoutSell">
                    ✅ KZT отправлены — завершить заявку
                </button>
            </div>

            <div v-if="canReject">
                <button
                    v-if="!showReject"
                    class="rounded-xl bg-surface-container px-4 py-2 text-sm font-semibold text-red-400"
                    @click="showReject = true"
                >
                    ❌ Отклонить заявку
                </button>
                <div v-else class="space-y-2">
                    <textarea v-model="rejectForm.reason" class="input-field" rows="2" placeholder="Причина отклонения *" />
                    <p v-if="rejectForm.errors.reason" class="text-sm text-red-400">{{ rejectForm.errors.reason }}</p>
                    <div class="flex gap-2">
                        <button class="rounded-xl bg-red-500/20 px-4 py-2 text-sm font-semibold text-red-400" :disabled="rejectForm.processing" @click="reject">
                            Подтвердить отклонение
                        </button>
                        <button class="rounded-xl bg-surface-container px-4 py-2 text-sm text-text-dim" @click="showReject = false">
                            Отмена
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <Link href="/admin/orders" class="mt-6 block text-sm text-text-dim">← К списку заявок</Link>
    </AdminLayout>
</template>
