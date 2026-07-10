<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import AppIcon from '@/shared/ui/icon/AppIcon.vue';
import PaymentProofPreview from '@/shared/ui/payment-proof/PaymentProofPreview.vue';
import SupportChatFab from '@/widgets/support-chat/ui/SupportChatFab.vue';
import { useOrderCountdown } from '@/composables/useOrderCountdown';
import { formatKzt, formatUsdt } from '@/utils/formatNumber';
import { localizedPath } from '@/utils/localizedPath';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';

const props = defineProps({
    order: Object,
    paymentRequest: Object,
    companyRequisites: {
        type: Object,
        default: () => ({}),
    },
    timers: {
        type: Object,
        default: () => ({}),
    },
    paymentProof: {
        type: Object,
        default: null,
    },
});

const page = usePage();
const copiedField = ref(null);
const showCancelModal = ref(false);
const showAppealModal = ref(false);
const markingPaid = ref(false);
const markingReceived = ref(false);
const refreshingStatus = ref(false);
const cancelReason = ref('Передумал(а)');
const cancelReasonOther = ref('');

let statusPollTimer = null;

const isBuy = computed(() => props.order.direction === 'buy');
const modeClass = computed(() => (isBuy.value ? 'exchange-mode--buy' : 'exchange-mode--sell'));
const isCompleted = computed(() => props.order.status === 'completed');
const isCancelled = computed(() => ['cancelled', 'failed'].includes(props.order.status));

const activeStep = computed(() => {
    if (isCompleted.value) {
        return 3;
    }

    if (isBuy.value) {
        if (props.order.status === 'awaiting_kzt_payment') {
            return 1;
        }

        if (['payment_proof_uploaded', 'pending_admin_confirmation'].includes(props.order.status)) {
            return 2;
        }
    } else {
        if (props.order.status === 'pending_admin_confirmation') {
            return 1;
        }

        if (props.order.status === 'kzt_sent') {
            return 2;
        }
    }

    return 1;
});

const stepItems = computed(() => [
    { id: 1, label: 'Ожидание' },
    { id: 2, label: 'Подтверждение' },
    { id: 3, label: 'Готово' },
]);

const paymentDeadline = computed(() => {
    if (isCompleted.value || isCancelled.value) {
        return null;
    }

    return props.timers.payment_deadline ?? null;
});

const confirmationDeadline = computed(() => {
    if (isCompleted.value || isCancelled.value) {
        return null;
    }

    if (isBuy.value && activeStep.value >= 2) {
        return props.timers.confirmation_deadline ?? null;
    }

    return null;
});

const countdownDeadline = computed(() => {
    if (activeStep.value === 1) {
        return paymentDeadline.value;
    }

    if (activeStep.value === 2) {
        return confirmationDeadline.value ?? paymentDeadline.value;
    }

    return null;
});

const { formatted: countdownLabel, active: countdownActive, expired: countdownExpired } = useOrderCountdown(countdownDeadline);

function refreshStatus({ silent = false } = {}) {
    if (refreshingStatus.value) {
        return;
    }

    refreshingStatus.value = true;
    router.reload({
        only: ['order', 'timers', 'paymentProof', 'paymentRequest'],
        preserveScroll: true,
        onFinish: () => {
            refreshingStatus.value = false;
        },
        ...(silent ? {} : { preserveState: true }),
    });
}

onMounted(() => {
    statusPollTimer = window.setInterval(() => {
        if (isCompleted.value || isCancelled.value) {
            return;
        }

        if (isBuy.value && activeStep.value < 2 && !countdownExpired.value) {
            return;
        }

        refreshStatus({ silent: true });
    }, 5000);
});

watch(countdownExpired, (expired) => {
    if (expired && !isCompleted.value && !isCancelled.value) {
        refreshStatus({ silent: true });
    }
});

watch(
    () => props.order.status,
    (status, previous) => {
        if (!isBuy.value && previous === 'pending_admin_confirmation' && status === 'kzt_sent') {
            refreshStatus({ silent: true });
        }
    },
);

onUnmounted(() => {
    if (statusPollTimer !== null) {
        window.clearInterval(statusPollTimer);
        statusPollTimer = null;
    }
});

const statusTitle = computed(() => {
    if (isCompleted.value) {
        return 'Заявка завершена';
    }

    if (isCancelled.value) {
        return props.order.status === 'failed' ? 'Ошибка заявки' : 'Заявка отменена';
    }

    if (isBuy.value) {
        if (activeStep.value === 1) {
            return 'Ожидание оплаты';
        }

        return 'Пользователь отметил оплату';
    }

    if (props.order.status === 'kzt_sent') {
        return 'Подтвердите получение KZT';
    }

    return 'Ожидание выплаты KZT';
});

const amountLabel = computed(() => {
    if (isCompleted.value) {
        return 'Сумма сделки';
    }

    return isBuy.value ? 'К оплате' : 'К получению';
});

const showPaymentRequisites = computed(
    () => isBuy.value && props.paymentRequest && !isCancelled.value,
);

const showPayoutRequisites = computed(
    () => !isBuy.value && props.paymentRequest && !isCancelled.value,
);

const requisitesTitle = computed(() => {
    if (isCompleted.value) {
        return 'Детали сделки';
    }

    return isBuy.value ? 'Реквизиты для оплаты' : 'Реквизиты для получения KZT';
});

const requisitesRows = computed(() => {
    if (isBuy.value) {
        return [
            {
                key: 'bank',
                label: 'Название банка',
                value: props.paymentRequest?.bank_name ?? props.companyRequisites.bank_name,
            },
            {
                key: 'account',
                label: 'Номер счёта',
                value: props.paymentRequest?.recipient_account ?? props.companyRequisites.recipient_account,
            },
            {
                key: 'bin',
                label: 'БИН',
                value: props.companyRequisites.bin,
            },
            {
                key: 'kbe',
                label: 'КБе',
                value: props.companyRequisites.kbe,
            },
        ].filter((row) => row.value);
    }

    return [
        {
            key: 'bank',
            label: 'Название банка',
            value: props.paymentRequest?.bank_name,
        },
        {
            key: 'account',
            label: 'Номер счёта',
            value: props.paymentRequest?.recipient_account,
        },
        {
            key: 'recipient',
            label: 'Получатель',
            value: props.paymentRequest?.recipient_name,
        },
    ].filter((row) => row.value);
});

const canMarkPaid = computed(
    () => isBuy.value && props.order.status === 'awaiting_kzt_payment',
);

const isWaitingSellPayout = computed(
    () => !isBuy.value && props.order.status === 'pending_admin_confirmation',
);

const isWaitingSellReceipt = computed(
    () => !isBuy.value && props.order.status === 'kzt_sent',
);

const canMarkReceived = computed(() => isWaitingSellReceipt.value);

const showSellRefreshCta = computed(
    () => !isBuy.value && !isCompleted.value && !isCancelled.value && !isWaitingSellReceipt.value,
);

const canCancel = computed(() =>
    isBuy.value
        ? ['awaiting_kzt_payment', 'payment_proof_uploaded', 'pending_admin_confirmation'].includes(props.order.status)
        : props.order.status === 'pending_admin_confirmation',
);

const canAppeal = computed(
    () => !isCompleted.value && !isCancelled.value && countdownActive.value && countdownExpired.value,
);

const chatUrl = computed(() => {
    const back = encodeURIComponent(`/exchange/orders/${props.order.id}`);

    return localizedPath(`/support/chat?order=${props.order.id}&back=${back}`);
});

const flashSuggestsChat = computed(() => {
    const message = page.props.flash?.success ?? '';

    return typeof message === 'string' && message.toLowerCase().includes('чат');
});

const instructionText = computed(() => {
    if (isCompleted.value) {
        return 'Сделка прошла через эскроу Kazakhstan Crypto Trust';
    }

    if (isBuy.value) {
        if (activeStep.value === 1) {
            return 'Переводите точную сумму с личного банковского счёта. Чек можно отправить в чат сделки.';
        }

        return 'Ожидайте подтверждения перевода продавцом. После подтверждения заявка будет завершена.';
    }

    if (props.order.status === 'kzt_sent') {
        return 'Проверьте поступление KZT на ваш счёт и подтвердите получение.';
    }

    return 'USDT заблокированы. Администратор переведёт KZT на указанные реквизиты.';
});

const orderSubtitle = computed(
    () => (isBuy.value ? 'Покупка USDT' : 'Продажа USDT'),
);

function markPaid() {
    markingPaid.value = true;
    router.post(route('exchange.orders.mark-paid', props.order.id), {}, {
        onFinish: () => {
            markingPaid.value = false;
        },
    });
}

function markReceived() {
    markingReceived.value = true;
    router.post(route('exchange.orders.mark-received', props.order.id), {}, {
        onFinish: () => {
            markingReceived.value = false;
        },
    });
}

function confirmCancel() {
    showCancelModal.value = false;
    const other = cancelReasonOther.value.trim();
    const reason = cancelReason.value === 'Другое' ? (other !== '' ? other : 'Другое') : cancelReason.value;

    router.post(route('exchange.orders.cancel', props.order.id), { reason });
}

async function copyText(text, field) {
    if (!text) {
        return;
    }

    try {
        await navigator.clipboard.writeText(text);
        copiedField.value = field;
        setTimeout(() => {
            if (copiedField.value === field) {
                copiedField.value = null;
            }
        }, 2000);
    } catch {
        // clipboard may be unavailable
    }
}
</script>

<template>
    <Head :title="`Заявка №${order.id}`" />

    <ExchangeLayout>
        <template #title>Заявка №{{ order.id }}</template>

        <div v-if="page.props.flash?.success" class="order-flash-success mb-4" role="status">
            <span class="material-symbols-outlined order-flash-success__icon" aria-hidden="true">check_circle</span>
            <div class="min-w-0 flex-1">
                <p class="order-flash-success__text">{{ page.props.flash.success }}</p>
                <Link v-if="flashSuggestsChat" :href="chatUrl" class="order-flash-success__action">
                    Открыть чат сделки →
                </Link>
            </div>
        </div>

        <div :class="modeClass" class="order-flow">
            <Link :href="localizedPath('/exchange')" class="order-flow__back" aria-label="Назад к обмену">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
            </Link>

            <p class="order-flow__subtitle">{{ orderSubtitle }}</p>

            <nav class="order-stepper" aria-label="Этапы сделки">
                <div
                    v-for="(step, index) in stepItems"
                    :key="step.id"
                    class="order-stepper__item"
                    :class="{
                        'order-stepper__item--done': activeStep > step.id || (isCompleted && step.id <= 3),
                        'order-stepper__item--active': activeStep === step.id && !isCompleted && !isCancelled,
                    }"
                >
                    <span class="order-stepper__dot">
                        <span
                            v-if="activeStep > step.id || (isCompleted && step.id < 3)"
                            class="material-symbols-outlined"
                            aria-hidden="true"
                        >
                            check
                        </span>
                        <template v-else>{{ step.id }}</template>
                    </span>
                    <span class="order-stepper__label">{{ step.label }}</span>
                    <span v-if="index < stepItems.length - 1" class="order-stepper__line" aria-hidden="true" />
                </div>
            </nav>

            <section
                v-if="!isCancelled"
                class="order-status-banner"
                :class="{
                    'order-status-banner--success': isCompleted,
                    'order-status-banner--pending': !isCompleted,
                }"
            >
                <div class="order-status-banner__left">
                    <span
                        v-if="isCompleted"
                        class="material-symbols-outlined order-status-banner__icon order-status-banner__icon--success"
                        aria-hidden="true"
                    >
                        check_circle
                    </span>
                    <div>
                        <p class="order-status-banner__label">{{ statusTitle }}</p>
                        <p
                            v-if="countdownActive && !countdownExpired && !isCompleted"
                            class="order-status-banner__timer"
                        >
                            {{ countdownLabel }}
                        </p>
                        <p
                            v-else-if="countdownExpired && !isCompleted && !isCancelled"
                            class="order-status-banner__timer order-status-banner__timer--expired"
                        >
                            Время истекло
                        </p>
                    </div>
                </div>
                <div class="order-status-banner__right">
                    <p class="order-status-banner__amount-label">{{ amountLabel }}</p>
                    <p class="order-status-banner__amount">{{ formatKzt(order.fiat_amount) }} ₸</p>
                </div>
            </section>

            <section v-else class="order-status-banner order-status-banner--error">
                <div>
                    <p class="order-status-banner__label">{{ statusTitle }}</p>
                    <p v-if="order.reject_reason" class="order-status-banner__note">{{ order.reject_reason }}</p>
                </div>
            </section>

            <section
                v-if="(showPaymentRequisites || showPayoutRequisites) && requisitesRows.length"
                class="order-requisites-card"
            >
                <h2 class="order-requisites-card__title">• {{ requisitesTitle }}</h2>

                <div
                    v-for="row in requisitesRows"
                    :key="row.key"
                    class="order-requisites-card__row"
                >
                    <div class="min-w-0 flex-1">
                        <p class="order-requisites-card__label">{{ row.label }}</p>
                        <p class="order-requisites-card__value">{{ row.value }}</p>
                    </div>
                    <button
                        type="button"
                        class="order-requisites-card__copy"
                        :aria-label="copiedField === row.key ? 'Скопировано' : 'Копировать'"
                        @click="copyText(row.value, row.key)"
                    >
                        <AppIcon :name="copiedField === row.key ? 'check' : 'copy'" :size="18" :stroke-width="2" />
                    </button>
                </div>

                <p v-if="isBuy && order.payment_bank_name" class="order-requisites-card__bank-note">
                    Банк оплаты: {{ order.payment_bank_name }}
                </p>
            </section>

            <PaymentProofPreview v-if="paymentProof" :proof="paymentProof" class="mb-4" />

            <div v-if="!isCancelled" class="order-flow__info">
                <span class="material-symbols-outlined text-base" aria-hidden="true">info</span>
                <p>{{ instructionText }}</p>
            </div>

            <div v-if="isBuy && activeStep === 1 && !isCompleted" class="order-flow__steps-list">
                <p>1. Переведите средства на указанные реквизиты.</p>
                <p>2. Нажмите «Я оплатил».</p>
                <p>3. Сохраните чек и отправьте его в чат сделки.</p>
            </div>

            <div v-if="isWaitingSellPayout && !isCompleted" class="order-flow__steps-list">
                <p>1. USDT заблокированы на вашем балансе.</p>
                <p>2. Ожидайте перевод KZT на реквизиты ниже.</p>
                <p>3. Статус обновится автоматически — затем подтвердите получение.</p>
            </div>

            <div v-if="isWaitingSellReceipt && !isCompleted" class="order-flow__steps-list">
                <p>1. Проверьте поступление {{ formatKzt(order.fiat_amount) }} ₸ на ваш счёт.</p>
                <p>2. Нажмите «Я получил KZT» для завершения сделки.</p>
            </div>

            <div v-if="order.listing_conditions && !isCompleted" class="order-flow__conditions">
                <p class="order-flow__conditions-title">Условия сделки</p>
                <p>{{ order.listing_conditions }}</p>
            </div>

            <p v-if="page.props.errors?.form" class="mb-4 text-sm text-red-500">
                {{ page.props.errors.form }}
            </p>

            <button
                v-if="canMarkPaid"
                type="button"
                class="btn-primary order-flow__cta"
                :disabled="markingPaid"
                @click="markPaid"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">check</span>
                Я оплатил
            </button>

            <button
                v-else-if="canMarkReceived"
                type="button"
                class="btn-primary order-flow__cta"
                :disabled="markingReceived"
                @click="markReceived"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">check</span>
                {{ markingReceived ? 'Подтверждаем…' : 'Я получил KZT' }}
            </button>

            <button
                v-else-if="showSellRefreshCta && isWaitingSellPayout"
                type="button"
                class="btn-primary order-flow__cta"
                :disabled="refreshingStatus"
                @click="refreshStatus()"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">refresh</span>
                {{ refreshingStatus ? 'Проверяем…' : 'Проверить статус выплаты' }}
            </button>

            <Link
                v-else-if="isCompleted"
                :href="localizedPath('/exchange')"
                class="btn-primary order-flow__cta"
            >
                Вернуться в обмен
            </Link>

            <Link
                v-if="!isCompleted && !isCancelled && !canMarkPaid"
                :href="chatUrl"
                class="order-flow__cta"
                :class="(canMarkReceived || isWaitingSellPayout || (isBuy && activeStep >= 2)) ? 'order-flow__cta--chat' : 'btn-primary order-flow__cta'"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">chat</span>
                {{ isBuy && activeStep >= 2 ? 'Открыть чат сделки' : 'Написать в чат сделки' }}
            </Link>

            <button
                v-if="isBuy && activeStep >= 2 && !isCompleted && !isCancelled"
                type="button"
                class="order-flow__appeal"
                :disabled="refreshingStatus"
                @click="refreshStatus()"
            >
                <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
                {{ refreshingStatus ? 'Проверяем…' : 'Проверить статус' }}
            </button>

            <div v-if="canAppeal || canCancel" class="order-flow__secondary-actions">
                <button
                    v-if="canAppeal"
                    type="button"
                    class="btn-primary order-flow__cta order-flow__cta--appeal"
                    @click="showAppealModal = true"
                >
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">warning</span>
                    Подать апелляцию
                </button>

                <button
                    v-if="canCancel"
                    type="button"
                    class="order-flow__cancel"
                    @click="showCancelModal = true"
                >
                    Отменить заявку
                </button>
            </div>

            <section v-if="isCompleted" class="order-flow__tx-card">
                <p class="order-flow__tx-label">ID транзакции</p>
                <div class="order-flow__tx-row">
                    <span class="order-flow__tx-value">TX{{ order.id }}</span>
                    <button
                        type="button"
                        class="order-requisites-card__copy"
                        aria-label="Копировать ID"
                        @click="copyText(`TX${order.id}`, 'tx')"
                    >
                        <AppIcon :name="copiedField === 'tx' ? 'check' : 'copy'" :size="18" :stroke-width="2" />
                    </button>
                </div>
            </section>
        </div>

        <SupportChatFab :order-id="order.id" :return-to="`/exchange/orders/${order.id}`" />

        <Teleport to="body">
            <div v-if="showCancelModal" class="sheet-backdrop" @click.self="showCancelModal = false">
                <div class="sheet-panel order-modal" role="dialog" aria-modal="true" aria-labelledby="cancel-title">
                    <div class="order-modal__icon order-modal__icon--danger">
                        <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    </div>
                    <h2 id="cancel-title" class="order-modal__title">Отмена заявки</h2>
                    <p class="order-modal__text">
                        При подтверждении отмены мы не несем ответственности за ваши дальнейшие операции с данными реквизитами.
                    </p>
                    <p class="order-modal__question">Причина отмены</p>
                    <div class="mt-3 space-y-2 text-left">
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="cancelReason" type="radio" value="Передумал(а)" />
                            <span>Передумал(а)</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="cancelReason" type="radio" value="Не получилось оплатить" />
                            <span>Не получилось оплатить</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="cancelReason" type="radio" value="Ошибка/неверные реквизиты" />
                            <span>Ошибка/неверные реквизиты</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="cancelReason" type="radio" value="Слишком долгое ожидание" />
                            <span>Слишком долгое ожидание</span>
                        </label>
                        <label class="flex items-start gap-2 text-sm">
                            <input v-model="cancelReason" type="radio" value="Другое" />
                            <span>Другое</span>
                        </label>
                        <input
                            v-if="cancelReason === 'Другое'"
                            v-model="cancelReasonOther"
                            type="text"
                            maxlength="120"
                            class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm"
                            placeholder="Введите причину..."
                        />
                    </div>
                    <button type="button" class="btn-primary order-modal__confirm" @click="confirmCancel">
                        Продолжить отмену
                    </button>
                    <button type="button" class="order-modal__back" @click="showCancelModal = false">
                        Вернуться
                    </button>
                </div>
            </div>

            <div v-if="showAppealModal" class="sheet-backdrop" @click.self="showAppealModal = false">
                <div class="sheet-panel order-modal" role="dialog" aria-modal="true" aria-labelledby="appeal-title">
                    <div class="order-modal__icon order-modal__icon--danger">
                        <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    </div>
                    <h2 id="appeal-title" class="order-modal__title">Подать апелляцию</h2>
                    <p class="order-modal__text">
                        Раздел апелляций пока в разработке. Если возникла проблема со сделкой, напишите в чат поддержки.
                    </p>
                    <Link :href="chatUrl" class="btn-primary order-modal__confirm" @click="showAppealModal = false">
                        Открыть чат
                    </Link>
                    <button type="button" class="order-modal__back" @click="showAppealModal = false">
                        Назад
                    </button>
                </div>
            </div>
        </Teleport>
    </ExchangeLayout>
</template>
