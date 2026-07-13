<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import AppIcon from '@/shared/ui/icon/AppIcon.vue';
import { useOrderStatusLabels } from '@/shared/lib/i18n/useOrderStatusLabels';
import PaymentProofPreview from '@/shared/ui/payment-proof/PaymentProofPreview.vue';
import SupportChatFab from '@/widgets/support-chat/ui/SupportChatFab.vue';
import { useOrderCountdown } from '@/composables/useOrderCountdown';
import { formatKzt, formatUsdt } from '@/utils/formatNumber';
import { localizedPath } from '@/utils/localizedPath';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

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
const { t } = useI18n();
const orderStatusLabels = useOrderStatusLabels();
const copiedField = ref(null);
const showCancelModal = ref(false);
const showAppealModal = ref(false);
const markingPaid = ref(false);
const markingReceived = ref(false);
const refreshingStatus = ref(false);
const cancelReason = ref('changed_mind');
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
    { id: 1, label: t('order.show.steps.waiting') },
    { id: 2, label: t('order.show.steps.confirmation') },
    { id: 3, label: t('order.show.steps.done') },
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
        return t('order.show.statusTitle.completed');
    }

    if (isCancelled.value) {
        return props.order.status === 'failed'
            ? t('order.show.statusTitle.failed')
            : t('order.show.statusTitle.cancelled');
    }

    if (isBuy.value) {
        if (activeStep.value === 1) {
            return t('order.show.statusTitle.awaitingPayment');
        }

        return t('order.show.statusTitle.paymentMarked');
    }

    if (props.order.status === 'kzt_sent') {
        return t('order.show.statusTitle.confirmReceipt');
    }

    return orderStatusLabels.value[props.order.status] ?? t('order.show.statusTitle.awaitingPayout');
});

const amountLabel = computed(() => {
    if (isCompleted.value) {
        return t('order.show.amountLabel.deal');
    }

    return isBuy.value ? t('order.show.amountLabel.toPay') : t('order.show.amountLabel.toReceive');
});

const showPaymentRequisites = computed(
    () => isBuy.value && props.paymentRequest && !isCancelled.value,
);

const showPayoutRequisites = computed(
    () => !isBuy.value && props.paymentRequest && !isCancelled.value,
);

const requisitesTitle = computed(() => {
    if (isCompleted.value) {
        return t('order.show.requisites.titleCompleted');
    }

    return isBuy.value ? t('order.show.requisites.titlePayment') : t('order.show.requisites.titlePayout');
});

const requisitesRows = computed(() => {
    if (isBuy.value) {
        return [
            {
                key: 'bank',
                label: t('order.show.requisites.labels.bank'),
                value: props.paymentRequest?.bank_name ?? props.companyRequisites.bank_name,
            },
            {
                key: 'account',
                label: t('order.show.requisites.labels.account'),
                value: props.paymentRequest?.recipient_account ?? props.companyRequisites.recipient_account,
            },
            {
                key: 'bin',
                label: t('order.show.requisites.labels.bin'),
                value: props.companyRequisites.bin,
            },
            {
                key: 'kbe',
                label: t('order.show.requisites.labels.kbe'),
                value: props.companyRequisites.kbe,
            },
        ].filter((row) => row.value);
    }

    return [
        {
            key: 'bank',
            label: t('order.show.requisites.labels.bank'),
            value: props.paymentRequest?.bank_name,
        },
        {
            key: 'account',
            label: t('order.show.requisites.labels.account'),
            value: props.paymentRequest?.recipient_account,
        },
        {
            key: 'recipient',
            label: t('order.show.requisites.labels.recipient'),
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
        && ['awaiting_kzt_payment', 'payment_proof_uploaded', 'pending_admin_confirmation'].includes(props.order.status),
);

const canAppeal = computed(
    () => !isCompleted.value && !isCancelled.value && countdownActive.value && countdownExpired.value,
);

const chatUrl = computed(() => {
    const back = encodeURIComponent(`/exchange/orders/${props.order.id}`);

    return localizedPath(`/support/chat?order=${props.order.id}&back=${back}`);
});

const chatKeyword = computed(() => t('order.show.chatKeyword').toLowerCase());

const flashSuggestsChat = computed(() => {
    const message = page.props.flash?.success ?? '';
    const normalized = typeof message === 'string' ? message.toLowerCase() : '';

    return normalized.includes(chatKeyword.value) || normalized.includes('chat');
});

const instructionText = computed(() => {
    if (isCompleted.value) {
        return t('order.show.instructions.completed');
    }

    if (isBuy.value) {
        if (activeStep.value === 1) {
            return t('order.show.instructions.buyStepOne');
        }

        return t('order.show.instructions.buyStepTwo');
    }

    if (props.order.status === 'kzt_sent') {
        return t('order.show.instructions.sellReceipt');
    }

    return t('order.show.instructions.sellPayout');
});

const orderSubtitle = computed(
    () => (isBuy.value ? t('order.show.subtitle.buy') : t('order.show.subtitle.sell')),
);

const cancelReasonOptions = computed(() => [
    { value: 'changed_mind', label: t('order.show.cancelModal.reasons.changedMind') },
    { value: 'payment_failed', label: t('order.show.cancelModal.reasons.cannotPay') },
    { value: 'wrong_requisites', label: t('order.show.cancelModal.reasons.wrongRequisites') },
    { value: 'long_wait', label: t('order.show.cancelModal.reasons.longWait') },
    { value: 'other', label: t('order.show.cancelModal.reasons.other') },
]);

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
    const selectedReason = cancelReasonOptions.value.find((option) => option.value === cancelReason.value);
    const reason = cancelReason.value === 'other'
        ? (other !== '' ? other : t('order.show.cancelModal.reasons.other'))
        : (selectedReason?.label ?? t('order.show.cancelModal.reasons.changedMind'));

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
    <Head :title="t('order.show.headTitle', { id: order.id })" />

    <ExchangeLayout>
        <template #title>{{ t('order.show.headTitle', { id: order.id }) }}</template>

        <div v-if="page.props.flash?.success" class="order-flash-success mb-4" role="status">
            <span class="material-symbols-outlined order-flash-success__icon" aria-hidden="true">check_circle</span>
            <div class="min-w-0 flex-1">
                <p class="order-flash-success__text">{{ page.props.flash.success }}</p>
                <Link v-if="flashSuggestsChat" :href="chatUrl" class="order-flash-success__action">
                    {{ t('order.show.actions.openDealChat') }}
                </Link>
            </div>
        </div>

        <div :class="modeClass" class="order-flow">
            <Link :href="localizedPath('/exchange')" class="order-flow__back" :aria-label="t('order.show.aria.backToExchange')">
                <span class="material-symbols-outlined" aria-hidden="true">arrow_back</span>
            </Link>

            <p class="order-flow__subtitle">{{ orderSubtitle }}</p>

            <nav class="order-stepper" :aria-label="t('order.show.aria.dealSteps')">
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
                            {{ t('order.show.timer.expired') }}
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
                        :aria-label="copiedField === row.key ? t('order.show.requisites.copied') : t('order.show.requisites.copy')"
                        @click="copyText(row.value, row.key)"
                    >
                        <AppIcon :name="copiedField === row.key ? 'check' : 'copy'" :size="18" :stroke-width="2" />
                    </button>
                </div>

                <p v-if="isBuy && order.payment_bank_name" class="order-requisites-card__bank-note">
                    {{ t('order.show.paymentBankNote', { bank: order.payment_bank_name }) }}
                </p>
            </section>

            <PaymentProofPreview v-if="paymentProof" :proof="paymentProof" class="mb-4" />

            <div v-if="!isCancelled" class="order-flow__info">
                <span class="material-symbols-outlined text-base" aria-hidden="true">info</span>
                <p>{{ instructionText }}</p>
            </div>

            <div v-if="isBuy && activeStep === 1 && !isCompleted" class="order-flow__steps-list">
                <p>{{ t('order.show.checklist.buy.one') }}</p>
                <p>{{ t('order.show.checklist.buy.two') }}</p>
                <p>{{ t('order.show.checklist.buy.three') }}</p>
            </div>

            <div v-if="isWaitingSellPayout && !isCompleted" class="order-flow__steps-list">
                <p>{{ t('order.show.checklist.sellPayout.one') }}</p>
                <p>{{ t('order.show.checklist.sellPayout.two') }}</p>
                <p>{{ t('order.show.checklist.sellPayout.three') }}</p>
            </div>

            <div v-if="isWaitingSellReceipt && !isCompleted" class="order-flow__steps-list">
                <p>{{ t('order.show.checklist.sellReceipt.one', { amount: formatKzt(order.fiat_amount) }) }}</p>
                <p>{{ t('order.show.checklist.sellReceipt.two') }}</p>
            </div>

            <div v-if="order.listing_conditions && !isCompleted" class="order-flow__conditions">
                <p class="order-flow__conditions-title">{{ t('order.show.conditionsTitle') }}</p>
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
                {{ t('order.show.actions.markPaid') }}
            </button>

            <button
                v-else-if="canMarkReceived"
                type="button"
                class="btn-primary order-flow__cta"
                :disabled="markingReceived"
                @click="markReceived"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">check</span>
                {{ markingReceived ? t('order.show.actions.confirming') : t('order.show.actions.markReceived') }}
            </button>

            <button
                v-else-if="showSellRefreshCta && isWaitingSellPayout"
                type="button"
                class="btn-primary order-flow__cta"
                :disabled="refreshingStatus"
                @click="refreshStatus()"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">refresh</span>
                {{ refreshingStatus ? t('order.show.actions.checking') : t('order.show.actions.checkPayoutStatus') }}
            </button>

            <Link
                v-else-if="isCompleted"
                :href="localizedPath('/exchange')"
                class="btn-primary order-flow__cta"
            >
                {{ t('order.show.actions.backToExchange') }}
            </Link>

            <Link
                v-if="!isCompleted && !isCancelled && !canMarkPaid"
                :href="chatUrl"
                class="order-flow__cta"
                :class="(canMarkReceived || isWaitingSellPayout || (isBuy && activeStep >= 2)) ? 'order-flow__cta--chat' : 'btn-primary order-flow__cta'"
            >
                <span class="material-symbols-outlined text-xl" aria-hidden="true">chat</span>
                {{ isBuy && activeStep >= 2 ? t('order.show.actions.openDealChat') : t('order.show.actions.writeDealChat') }}
            </Link>

            <button
                v-if="isBuy && activeStep >= 2 && !isCompleted && !isCancelled"
                type="button"
                class="order-flow__appeal"
                :disabled="refreshingStatus"
                @click="refreshStatus()"
            >
                <span class="material-symbols-outlined" aria-hidden="true">refresh</span>
                {{ refreshingStatus ? t('order.show.actions.checking') : t('order.show.actions.checkStatus') }}
            </button>

            <div v-if="canAppeal || canCancel" class="order-flow__secondary-actions">
                <button
                    v-if="canAppeal"
                    type="button"
                    class="btn-primary order-flow__cta order-flow__cta--appeal"
                    @click="showAppealModal = true"
                >
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">warning</span>
                    {{ t('order.show.actions.appeal') }}
                </button>

                <button
                    v-if="canCancel"
                    type="button"
                    class="order-flow__cancel"
                    @click="showCancelModal = true"
                >
                    {{ t('order.show.actions.cancelOrder') }}
                </button>
            </div>

            <section v-if="isCompleted" class="order-flow__tx-card">
                <p class="order-flow__tx-label">{{ t('order.show.txLabel') }}</p>
                <div class="order-flow__tx-row">
                    <span class="order-flow__tx-value">TX{{ order.id }}</span>
                    <button
                        type="button"
                        class="order-requisites-card__copy"
                        :aria-label="t('order.show.copyTxId')"
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
                    <h2 id="cancel-title" class="order-modal__title">{{ t('order.show.cancelModal.title') }}</h2>
                    <p class="order-modal__text">
                        {{ t('order.show.cancelModal.text') }}
                    </p>
                    <p class="order-modal__question">{{ t('order.show.cancelModal.question') }}</p>
                    <div class="mt-3 space-y-2 text-left">
                        <label
                            v-for="option in cancelReasonOptions"
                            :key="option.value"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input v-model="cancelReason" type="radio" :value="option.value" />
                            <span>{{ option.label }}</span>
                        </label>
                        <input
                            v-if="cancelReason === 'other'"
                            v-model="cancelReasonOther"
                            type="text"
                            maxlength="120"
                            class="w-full rounded-xl border border-outline-variant bg-surface px-3 py-2 text-sm"
                            :placeholder="t('order.show.cancelModal.placeholder')"
                        />
                    </div>
                    <button type="button" class="btn-primary order-modal__confirm" @click="confirmCancel">
                        {{ t('order.show.cancelModal.continue') }}
                    </button>
                    <button type="button" class="order-modal__back" @click="showCancelModal = false">
                        {{ t('order.show.cancelModal.back') }}
                    </button>
                </div>
            </div>

            <div v-if="showAppealModal" class="sheet-backdrop" @click.self="showAppealModal = false">
                <div class="sheet-panel order-modal" role="dialog" aria-modal="true" aria-labelledby="appeal-title">
                    <div class="order-modal__icon order-modal__icon--danger">
                        <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                    </div>
                    <h2 id="appeal-title" class="order-modal__title">{{ t('order.show.appealModal.title') }}</h2>
                    <p class="order-modal__text">
                        {{ t('order.show.appealModal.text') }}
                    </p>
                    <Link :href="chatUrl" class="btn-primary order-modal__confirm" @click="showAppealModal = false">
                        {{ t('order.show.appealModal.openChat') }}
                    </Link>
                    <button type="button" class="order-modal__back" @click="showAppealModal = false">
                        {{ t('order.show.appealModal.back') }}
                    </button>
                </div>
            </div>
        </Teleport>
    </ExchangeLayout>
</template>
