<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import PaymentProofPreview from '@/shared/ui/payment-proof/PaymentProofPreview.vue';
import { useSupportChat } from '@/composables/useSupportChat';
import { localizedPath } from '@/utils/localizedPath';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    orderId: {
        type: Number,
        required: true,
    },
    backUrl: {
        type: String,
        default: '/exchange',
    },
    canUploadProof: {
        type: Boolean,
        default: false,
    },
    needsPaymentProof: {
        type: Boolean,
        default: false,
    },
    paymentProof: {
        type: Object,
        default: null,
    },
});

const messagesEl = ref(null);
const proofInput = ref(null);
const uploadingProof = ref(false);
const proofError = ref(null);
const { t, locale } = useI18n();

const showProofHint = computed(() => props.needsPaymentProof && !props.paymentProof);

const {
    loading,
    sending,
    messages,
    draft,
    error,
    loadThread,
    sendMessage,
    startThreadPolling,
    stopPolling,
} = useSupportChat(props.orderId);

const backLabel = computed(() =>
    props.backUrl.startsWith('/exchange/orders/') ? t('support.chat.backToOrder') : t('common.back'),
);

const proofMessageMarker = computed(() => t('support.chat.proof.messageMarker').toLowerCase());

const proofAttachmentMessageId = computed(() => {
    if (!props.paymentProof) {
        return null;
    }

    const proofMessages = messages.value.filter(
        (message) => message.is_mine && message.body?.toLowerCase().includes(proofMessageMarker.value),
    );

    if (proofMessages.length > 0) {
        return proofMessages[proofMessages.length - 1].id;
    }

    const lastMineMessage = [...messages.value].reverse().find((message) => message.is_mine);

    return lastMineMessage?.id ?? null;
});

function shouldShowProofUnderMessage(message) {
    return props.paymentProof && message.id === proofAttachmentMessageId.value;
}

onMounted(async () => {
    await loadThread();
    startThreadPolling();
});

onUnmounted(() => {
    stopPolling();
});

watch(
    () => messages.value.length,
    async () => {
        await nextTick();
        scrollToBottom();
    },
);

function scrollToBottom() {
    const el = messagesEl.value;

    if (el) {
        el.scrollTop = el.scrollHeight;
    }
}

function formatTime(value) {
    if (!value) {
        return '';
    }

    const dateLocale = locale.value === 'kk' ? 'kk-KZ' : (locale.value === 'en' ? 'en-US' : 'ru-RU');

    return new Date(value).toLocaleString(dateLocale, {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function onSubmit() {
    sendMessage();
}

function openProofPicker() {
    proofInput.value?.click();
}

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function uploadProofFile(file) {
    const formData = new FormData();
    formData.append('proof', file);

    const response = await fetch(route('exchange.orders.proof', props.orderId), {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': csrfToken(),
        },
        body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const proofErrors = data.errors?.proof;
        const proofMessage = Array.isArray(proofErrors) ? proofErrors[0] : proofErrors;

        throw new Error(proofMessage ?? data.message ?? t('support.chat.proof.uploadFailed'));
    }

    return data;
}

function onProofSelected(event) {
    const file = event.target.files?.[0];

    if (!file || uploadingProof.value) {
        return;
    }

    uploadingProof.value = true;
    proofError.value = null;

    uploadProofFile(file)
        .then(async () => {
            draft.value = t('support.chat.proof.sentMessage');
            await sendMessage();
            await loadThread({ silent: true });
            router.reload({ only: ['paymentProof', 'needsPaymentProof', 'canUploadProof'], preserveScroll: true });
        })
        .catch((exception) => {
            proofError.value = exception.message;
        })
        .finally(() => {
            uploadingProof.value = false;
            event.target.value = '';
        });
}
</script>

<template>
    <Head :title="t('support.chat.headTitle')" />

    <ExchangeLayout hide-header flush-main>
        <div class="support-chat-layout support-chat-layout--fullscreen">
            <section class="support-chat-page support-chat-page--fullscreen" :aria-label="t('support.chat.aria.section')">
                <header class="support-chat-page__header support-chat-page__header--with-back">
                    <Link
                        :href="localizedPath(backUrl)"
                        class="support-chat-page__back"
                        :aria-label="backLabel"
                    >
                        <span class="material-symbols-outlined text-xl" aria-hidden="true">arrow_back</span>
                    </Link>
                    <div class="min-w-0 flex-1">
                        <p class="support-chat-page__title">{{ t('support.chat.title', { id: orderId }) }}</p>
                        <p class="support-chat-page__subtitle">{{ t('support.chat.subtitle') }}</p>
                    </div>
                </header>

                <div
                    v-if="showProofHint"
                    class="support-chat-page__proof-hint"
                    role="note"
                >
                    <span class="material-symbols-outlined support-chat-page__proof-hint-icon" aria-hidden="true">receipt_long</span>
                    <div class="min-w-0 flex-1">
                        <p class="support-chat-page__proof-hint-title">{{ t('support.chat.proof.hintTitle') }}</p>
                        <p class="support-chat-page__proof-hint-text">
                            {{ t('support.chat.proof.hintTextBefore') }}
                            <span class="material-symbols-outlined align-middle text-base" aria-hidden="true">attach_file</span>
                            {{ t('support.chat.proof.hintTextAfter') }}
                        </p>
                    </div>
                </div>

                <div ref="messagesEl" class="support-chat-page__messages">
                    <p v-if="loading && messages.length === 0" class="support-chat-page__empty">{{ t('common.loading') }}</p>
                    <p v-else-if="messages.length === 0 && showProofHint" class="support-chat-page__empty">
                        {{ t('support.chat.empty.needProof') }}
                    </p>
                    <p v-else-if="messages.length === 0" class="support-chat-page__empty">
                        {{ t('support.chat.empty.default') }}
                    </p>
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        class="support-chat-message-group"
                        :class="message.is_mine ? 'support-chat-message-group--mine' : 'support-chat-message-group--theirs'"
                    >
                        <article
                            class="support-chat-message"
                            :class="message.is_mine ? 'support-chat-message--mine' : 'support-chat-message--theirs'"
                        >
                            <p class="support-chat-message__body">{{ message.body }}</p>
                            <p class="support-chat-message__meta">{{ formatTime(message.created_at) }}</p>
                        </article>

                        <PaymentProofPreview
                            v-if="shouldShowProofUnderMessage(message)"
                            :proof="paymentProof"
                            inline
                        />
                    </div>
                </div>
            </section>

            <form class="support-chat-page__composer support-chat-page__composer--fullscreen" @submit.prevent="onSubmit">
                <div class="support-chat-page__composer-row">
                    <button
                        v-if="canUploadProof"
                        type="button"
                        class="support-chat-page__attach"
                        :disabled="uploadingProof"
                        :aria-label="t('support.chat.proof.attachAria')"
                        @click="openProofPicker"
                    >
                        <span
                            class="material-symbols-outlined"
                            :class="{ 'animate-spin': uploadingProof }"
                            aria-hidden="true"
                        >
                            {{ uploadingProof ? 'progress_activity' : 'attach_file' }}
                        </span>
                    </button>
                    <input
                        ref="proofInput"
                        type="file"
                        accept="image/*,.pdf"
                        class="sr-only"
                        @change="onProofSelected"
                    />
                    <textarea
                        v-model="draft"
                        rows="1"
                        maxlength="2000"
                        class="support-chat-page__input"
                        :placeholder="showProofHint ? '' : t('support.chat.messagePlaceholder')"
                        :disabled="sending"
                        @keydown.enter.exact.prevent="onSubmit"
                    />
                    <button
                        type="submit"
                        class="support-chat-page__send"
                        :disabled="sending || !draft.trim()"
                        :aria-label="t('support.chat.sendAria')"
                    >
                        <span class="material-symbols-outlined" aria-hidden="true">send</span>
                    </button>
                </div>
                <p v-if="proofError" class="support-chat-page__error">{{ proofError }}</p>
                <p v-if="error" class="support-chat-page__error">{{ error }}</p>
            </form>
        </div>
    </ExchangeLayout>
</template>
