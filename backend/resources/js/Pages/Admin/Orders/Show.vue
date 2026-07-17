<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import PaymentProofPreview from '@/shared/ui/payment-proof/PaymentProofPreview.vue';
import { statusTagColor } from '@/shared/lib/admin/tagColors';
import { formatKzt, formatPercent, formatRate, formatUsdt } from '@/utils/formatNumber';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    order: Object,
    paymentRequest: Object,
    paymentProof: {
        type: Object,
        default: null,
    },
    timers: {
        type: Object,
        default: () => ({}),
    },
    activeAppeal: {
        type: Object,
        default: null,
    },
    canAppeal: {
        type: Boolean,
        default: false,
    },
    appealReasons: {
        type: Array,
        default: () => [],
    },
});

const showConfirmModal = ref(false);
const showPayoutModal = ref(false);
const showRejectModal = ref(false);
const showAppealModal = ref(false);
const appealFileInput = ref(null);

const confirmForm = useForm({});
const payoutForm = useForm({});
const rejectForm = useForm({ reason: '' });
const appealForm = useForm({
    reason: '',
    description: '',
    attachments: [],
});
const { t } = useI18n();

const statusLabels = computed(() => ({
    created: t('admin.orders.status.created'),
    awaiting_kzt_payment: t('admin.orders.status.awaiting_kzt_payment'),
    payment_proof_uploaded: t('admin.orders.status.payment_proof_uploaded'),
    pending_admin_confirmation: t('admin.orders.status.pending_admin_confirmation'),
    kzt_sent: t('admin.orders.status.kzt_sent'),
    kzt_received: t('admin.orders.status.kzt_received'),
    completed: t('admin.orders.status.completed'),
    cancelled: t('admin.orders.status.cancelled'),
    failed: t('admin.orders.status.failed'),
    dispute: t('admin.orders.status.dispute'),
    manual_review: t('admin.orders.status.manual_review'),
}));

const isBuy = computed(() => props.order.direction === 'buy');
const isDispute = computed(() => props.order.status === 'dispute');
const hasActiveAppeal = computed(() => props.activeAppeal?.status === 'open');

const canConfirmBuy = computed(
    () => !isDispute.value
        && isBuy.value
        && ['awaiting_kzt_payment', 'payment_proof_uploaded', 'pending_admin_confirmation'].includes(props.order.status),
);

const canPayoutSell = computed(
    () => !isDispute.value && !isBuy.value && props.order.status === 'pending_admin_confirmation',
);

const canReject = computed(() => !isDispute.value && (canConfirmBuy.value || canPayoutSell.value));
const canAppealButton = computed(() => props.canAppeal && !hasActiveAppeal.value);

const hasActions = computed(() => canConfirmBuy.value || canPayoutSell.value || canReject.value || canAppealButton.value);

const appealReasonOptions = computed(() =>
    (props.appealReasons ?? []).map((value) => ({
        value,
        label: t(`admin.appeals.reasons.${value}`, value),
    })),
);

const appealFilesLabel = computed(() => {
    const count = appealForm.attachments?.length ?? 0;

    if (count === 0) {
        return t('admin.orders.show.modals.appeal.uploadEmpty');
    }

    return t('admin.orders.show.modals.appeal.uploadSelected', { count });
});

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

function openAppealFilePicker() {
    appealFileInput.value?.click();
}

function onAppealFilesSelected(event) {
    const files = Array.from(event.target.files ?? []).slice(0, 5);
    appealForm.attachments = files;
}

function submitAppeal() {
    if (!appealForm.reason) {
        return;
    }

    appealForm.post(`/admin/orders/${props.order.id}/appeal`, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            showAppealModal.value = false;
            appealForm.reset();
            appealForm.clearErrors();

            if (appealFileInput.value) {
                appealFileInput.value.value = '';
            }
        },
    });
}

function formatDate(value) {
    return value ? new Date(value).toLocaleString('ru-RU') : t('admin.shared.empty');
}
</script>

<template>
    <Head :title="t('admin.orders.show.headTitle', { id: order.id })" />

    <AdminLayout>
        <template #title>
            {{
                t('admin.orders.show.title', {
                    id: order.id,
                    direction: t(order.direction === 'buy' ? 'admin.shared.direction.buyLower' : 'admin.shared.direction.sellLower'),
                })
            }}
        </template>

        <AdminPage>
            <a-alert
                v-if="hasActiveAppeal"
                type="warning"
                show-icon
                class="mb-4"
                :message="t('admin.appeals.statuses.open')"
                :description="t('admin.appeals.show.description')"
            >
                <template v-if="activeAppeal?.id" #action>
                    <Link :href="`/admin/appeals/${activeAppeal.id}`">
                        <a-button size="small">{{ t('admin.shared.actions.open') }}</a-button>
                    </Link>
                </template>
            </a-alert>

            <div v-if="hasActions" class="admin-ant-sticky-actions">
                <a-button v-if="canConfirmBuy" type="primary" @click="openConfirm">
                    {{ t('admin.orders.show.actions.confirmPayment') }}
                </a-button>
                <a-button v-if="canPayoutSell" type="primary" @click="openPayout">
                    {{ t('admin.orders.show.actions.completePayout') }}
                </a-button>
                <a-button v-if="canAppealButton" @click="showAppealModal = true">
                    {{ t('admin.orders.show.actions.appeal') }}
                </a-button>
                <a-button v-if="canReject" danger @click="showRejectModal = true">
                    {{ t('admin.orders.show.actions.reject') }}
                </a-button>
            </div>

            <a-row
                :gutter="[16, 16]"
                class="admin-orders-show__body"
                :class="{ 'admin-orders-show__body--with-actions': hasActions }"
            >
                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.orders.show.cards.details')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.orders.show.labels.status')">
                                <a-tag :color="statusTagColor(order.status)">
                                    {{ statusLabels[order.status] ?? order.status }}
                                </a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.client')">
                                {{ order.user?.name }} · {{ order.user?.phone }}
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.kyc')">{{ order.user?.kyc_status }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.kzt')">
                                <a-typography-text strong>{{ formatKzt(order.fiat_amount) }} ₸</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.usdt')">
                                <a-typography-text strong>{{ formatUsdt(order.crypto_amount, 4) }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.rate')">{{ formatRate(order.rate) }} ₸/USDT</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.fee')">
                                {{ formatUsdt(order.fee_amount, 4) }} ({{ formatPercent(order.fee_percent) }}%)
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.created')">{{ formatDate(order.created_at) }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.completed')">{{ formatDate(order.completed_at) }}</a-descriptions-item>
                            <a-descriptions-item v-if="order.reject_reason" :label="t('admin.orders.show.labels.rejectReason')">
                                <a-typography-text type="danger">{{ order.reject_reason }}</a-typography-text>
                            </a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card
                        :title="isBuy ? t('admin.orders.show.cards.paymentBuy') : t('admin.orders.show.cards.paymentSell')"
                        size="small"
                    >
                        <a-descriptions v-if="paymentRequest" :column="1" size="small">
                            <a-descriptions-item v-if="order.payment_bank_name" :label="t('admin.orders.show.labels.clientBank')">
                                {{ order.payment_bank_name }}
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.recipientBank')">{{ paymentRequest.bank_name }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.recipient')">{{ paymentRequest.recipient_name }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.account')">
                                <a-typography-text code>{{ paymentRequest.recipient_account }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.amount')">
                                <a-typography-text strong>{{ formatKzt(paymentRequest.amount) }} ₸</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item :label="t('admin.orders.show.labels.paymentStatus')">{{ paymentRequest.status }}</a-descriptions-item>
                        </a-descriptions>

                        <template v-if="isBuy">
                            <PaymentProofPreview
                                v-if="paymentProof"
                                :proof="paymentProof"
                                class="admin-ant-block"
                            />
                            <a-typography-text v-else type="secondary">{{ t('admin.orders.show.noScreenshot') }}</a-typography-text>
                        </template>
                    </a-card>
                </a-col>
            </a-row>

            <a-modal
                v-model:open="showConfirmModal"
                :title="t('admin.orders.show.modals.confirm.title')"
                :ok-text="t('admin.shared.actions.yes')"
                :cancel-text="t('admin.shared.actions.no')"
                :confirm-loading="confirmForm.processing"
                width="420px"
                destroy-on-close
                @ok="confirmBuy"
            >
                <a-typography-paragraph class="!mb-0">
                    {{ t('admin.orders.show.modals.confirm.body', { amount: formatKzt(order.fiat_amount) }) }}
                </a-typography-paragraph>
            </a-modal>

            <a-modal
                v-model:open="showPayoutModal"
                :title="t('admin.orders.show.modals.payout.title')"
                :ok-text="t('admin.shared.actions.yes')"
                :cancel-text="t('admin.shared.actions.no')"
                :confirm-loading="payoutForm.processing"
                width="420px"
                destroy-on-close
                @ok="payoutSell"
            >
                <a-typography-paragraph class="!mb-0">
                    {{ t('admin.orders.show.modals.payout.body', { amount: formatKzt(order.fiat_amount) }) }}
                </a-typography-paragraph>
            </a-modal>

            <a-modal
                v-model:open="showRejectModal"
                :title="t('admin.orders.show.modals.reject.title')"
                :ok-text="t('admin.shared.actions.confirm')"
                :cancel-text="t('admin.shared.actions.cancel')"
                ok-type="danger"
                :confirm-loading="rejectForm.processing"
                destroy-on-close
                @ok="reject"
            >
                <a-form layout="vertical">
                    <a-form-item :label="t('admin.orders.show.modals.reject.reasonLabel')" required>
                        <a-textarea
                            v-model:value="rejectForm.reason"
                            :rows="3"
                            :placeholder="t('admin.orders.show.modals.reject.reasonPlaceholder')"
                        />
                    </a-form-item>
                </a-form>
            </a-modal>

            <a-modal
                v-model:open="showAppealModal"
                :title="t('admin.orders.show.modals.appeal.title')"
                :ok-text="t('admin.orders.show.modals.appeal.submit')"
                :cancel-text="t('admin.shared.actions.cancel')"
                :confirm-loading="appealForm.processing"
                :ok-button-props="{ disabled: !appealForm.reason }"
                width="520px"
                destroy-on-close
                @ok="submitAppeal"
            >
                <a-typography-paragraph>{{ t('admin.orders.show.modals.appeal.text') }}</a-typography-paragraph>

                <a-form layout="vertical">
                    <a-form-item :label="t('admin.orders.show.modals.appeal.reasonLabel')" required>
                        <a-radio-group v-model:value="appealForm.reason" class="flex flex-col gap-2">
                            <a-radio v-for="option in appealReasonOptions" :key="option.value" :value="option.value">
                                {{ option.label }}
                            </a-radio>
                        </a-radio-group>
                    </a-form-item>

                    <a-form-item :label="t('admin.orders.show.modals.appeal.descriptionLabel')">
                        <a-textarea
                            v-model:value="appealForm.description"
                            :rows="4"
                            maxlength="500"
                            :placeholder="t('admin.orders.show.modals.appeal.descriptionPlaceholder')"
                        />
                    </a-form-item>

                    <a-form-item :label="t('admin.orders.show.modals.appeal.uploadLabel')">
                        <a-typography-text type="secondary" class="block mb-2">
                            {{ t('admin.orders.show.modals.appeal.uploadHint') }}
                        </a-typography-text>
                        <a-button @click="openAppealFilePicker">{{ appealFilesLabel }}</a-button>
                        <input
                            ref="appealFileInput"
                            type="file"
                            accept="image/*,.pdf"
                            multiple
                            class="sr-only"
                            @change="onAppealFilesSelected"
                        />
                    </a-form-item>
                </a-form>
            </a-modal>

            <AdminBackLink href="/admin/orders" :label="t('admin.orders.show.backToList')" />
        </AdminPage>
    </AdminLayout>
</template>

<style scoped>
@media (max-width: 767px) {
    .admin-orders-show__body--with-actions {
        padding-bottom: calc(140px + env(safe-area-inset-bottom) + var(--admin-pwa-banner-offset, 0px));
    }
}
</style>
