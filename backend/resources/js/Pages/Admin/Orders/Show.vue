<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import PaymentProofPreview from '@/shared/ui/payment-proof/PaymentProofPreview.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatKzt, formatPercent, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    order: Object,
    paymentRequest: Object,
    paymentProof: {
        type: Object,
        default: null,
    },
});

const showConfirmModal = ref(false);
const showPayoutModal = ref(false);
const showRejectModal = ref(false);

const confirmForm = useForm({});
const payoutForm = useForm({});
const rejectForm = useForm({ reason: '' });

const statusLabels = {
    created: 'Создана',
    awaiting_kzt_payment: 'Ждёт оплату KZT',
    payment_proof_uploaded: 'Скрин загружен',
    pending_admin_confirmation: 'Ждёт подтверждения',
    kzt_sent: 'KZT отправлены',
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

const hasActions = computed(() => canConfirmBuy.value || canPayoutSell.value || canReject.value);

function openConfirm() {
    confirmForm.reset();
    showConfirmModal.value = true;
}

function openPayout() {
    payoutForm.reset();
    showPayoutModal.value = true;
}

function confirmBuy() {
    confirmForm.post(`/admin/orders/${props.order.id}/confirm-payment`, {
        onSuccess: () => {
            showConfirmModal.value = false;
        },
    });
}

function payoutSell() {
    payoutForm.post(`/admin/orders/${props.order.id}/mark-kzt-sent`, {
        onSuccess: () => {
            showPayoutModal.value = false;
        },
    });
}

function reject() {
    rejectForm.post(`/admin/orders/${props.order.id}/reject`, {
        onSuccess: () => {
            showRejectModal.value = false;
        },
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : '—';
}
</script>

<template>
    <Head :title="`Заявка №${order.id}`" />

    <AdminLayout>
        <template #title>Заявка №{{ order.id }} — {{ order.direction === 'buy' ? 'покупка' : 'продажа' }}</template>

        <AdminPage>
            <a-space v-if="hasActions" class="admin-ant-block">
                <a-button v-if="canConfirmBuy" type="primary" @click="openConfirm">
                    Подтвердить оплату
                </a-button>
                <a-button v-if="canPayoutSell" type="primary" @click="openPayout">
                    Завершить выплату
                </a-button>
                <a-button v-if="canReject" danger @click="showRejectModal = true">
                    Отклонить заявку
                </a-button>
            </a-space>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card title="Детали заявки" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Статус">
                                <a-tag :color="statusTagColor(order.status)">
                                    {{ statusLabels[order.status] ?? order.status }}
                                </a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item label="Клиент">
                                {{ order.user?.name }} · {{ order.user?.phone }}
                            </a-descriptions-item>
                            <a-descriptions-item label="KYC">{{ order.user?.kyc_status }}</a-descriptions-item>
                            <a-descriptions-item label="KZT">
                                <a-typography-text strong>{{ formatKzt(order.fiat_amount) }} ₸</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item label="USDT">
                                <a-typography-text strong>{{ formatUsdt(order.crypto_amount, 4) }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item label="Курс">{{ formatRate(order.rate) }} ₸/USDT</a-descriptions-item>
                            <a-descriptions-item label="Комиссия">
                                {{ formatUsdt(order.fee_amount, 4) }} ({{ formatPercent(order.fee_percent) }}%)
                            </a-descriptions-item>
                            <a-descriptions-item label="Создана">{{ formatDate(order.created_at) }}</a-descriptions-item>
                            <a-descriptions-item label="Завершена">{{ formatDate(order.completed_at) }}</a-descriptions-item>
                            <a-descriptions-item v-if="order.reject_reason" label="Причина отмены">
                                <a-typography-text type="danger">{{ order.reject_reason }}</a-typography-text>
                            </a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card
                        :title="isBuy ? 'Оплата клиента (KZT → обменник)' : 'Выплата клиенту (обменник → KZT)'"
                        size="small"
                    >
                        <a-descriptions v-if="paymentRequest" :column="1" size="small">
                            <a-descriptions-item v-if="order.payment_bank_name" label="Банк клиента">
                                {{ order.payment_bank_name }}
                            </a-descriptions-item>
                            <a-descriptions-item label="Банк получателя">{{ paymentRequest.bank_name }}</a-descriptions-item>
                            <a-descriptions-item label="Получатель">{{ paymentRequest.recipient_name }}</a-descriptions-item>
                            <a-descriptions-item label="Счёт / карта">
                                <a-typography-text code>{{ paymentRequest.recipient_account }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item label="Сумма">
                                <a-typography-text strong>{{ formatKzt(paymentRequest.amount) }} ₸</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item label="Статус оплаты">{{ paymentRequest.status }}</a-descriptions-item>
                        </a-descriptions>

                        <template v-if="isBuy">
                            <PaymentProofPreview
                                v-if="paymentProof"
                                :proof="paymentProof"
                                class="admin-ant-block"
                            />
                            <a-typography-text v-else type="secondary">Скрин оплаты ещё не загружен.</a-typography-text>
                        </template>
                    </a-card>
                </a-col>
            </a-row>

            <!-- Подтверждение покупки -->
            <a-modal
                v-model:open="showConfirmModal"
                title="Подтвердить оплату"
                ok-text="Да"
                cancel-text="Нет"
                :confirm-loading="confirmForm.processing"
                width="420px"
                destroy-on-close
                @ok="confirmBuy"
            >
                <a-typography-paragraph class="!mb-0">
                    Подтвердить получение <strong>{{ formatKzt(order.fiat_amount) }} ₸</strong> и зачислить USDT клиенту?
                </a-typography-paragraph>
            </a-modal>

            <!-- Завершение продажи -->
            <a-modal
                v-model:open="showPayoutModal"
                title="Завершить выплату KZT"
                ok-text="Да"
                cancel-text="Нет"
                :confirm-loading="payoutForm.processing"
                width="420px"
                destroy-on-close
                @ok="payoutSell"
            >
                <a-typography-paragraph class="!mb-0">
                    Подтвердить, что <strong>{{ formatKzt(order.fiat_amount) }} ₸</strong> отправлены клиенту?
                </a-typography-paragraph>
            </a-modal>

            <!-- Отклонение -->
            <a-modal
                v-model:open="showRejectModal"
                title="Отклонить заявку"
                ok-text="Подтвердить"
                cancel-text="Отмена"
                ok-type="danger"
                :confirm-loading="rejectForm.processing"
                destroy-on-close
                @ok="reject"
            >
                <a-form layout="vertical">
                    <a-form-item label="Причина отклонения" required>
                        <a-textarea v-model:value="rejectForm.reason" :rows="3" placeholder="Причина отклонения *" />
                    </a-form-item>
                </a-form>
            </a-modal>

            <AdminBackLink href="/admin/orders" label="К списку заявок" />
        </AdminPage>
    </AdminLayout>
</template>
