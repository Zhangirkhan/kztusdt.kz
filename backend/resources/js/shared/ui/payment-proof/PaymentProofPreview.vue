<script setup>
import { ref } from 'vue';

const props = defineProps({
    proof: {
        type: Object,
        required: true,
    },
    compact: {
        type: Boolean,
        default: false,
    },
    inline: {
        type: Boolean,
        default: false,
    },
});

const showPreview = ref(false);

function openProof() {
    if (props.proof.is_image) {
        showPreview.value = true;
        return;
    }

    window.open(props.proof.url, '_blank', 'noopener,noreferrer');
}
</script>

<template>
    <article
        class="payment-proof-card"
        :class="{
            'payment-proof-card--compact': compact,
            'payment-proof-card--inline': inline,
        }"
    >
        <button type="button" class="payment-proof-card__open" @click="openProof">
            <span
                v-if="proof.is_image"
                class="payment-proof-card__thumb"
                :style="{ backgroundImage: `url(${proof.url})` }"
                aria-hidden="true"
            />
            <span v-else class="payment-proof-card__file-icon" aria-hidden="true">
                <span class="material-symbols-outlined">{{ proof.is_pdf ? 'picture_as_pdf' : 'attach_file' }}</span>
            </span>

            <span class="payment-proof-card__body">
                <span class="payment-proof-card__label">Скриншот оплаты</span>
                <span class="payment-proof-card__filename">{{ proof.filename }}</span>
                <span class="payment-proof-card__action">
                    {{ proof.is_image ? 'Нажмите, чтобы открыть' : 'Открыть файл' }}
                </span>
            </span>

            <span class="material-symbols-outlined payment-proof-card__chevron" aria-hidden="true">chevron_right</span>
        </button>

        <Teleport to="body">
            <div
                v-if="showPreview && proof.is_image"
                class="payment-proof-modal"
                role="dialog"
                aria-modal="true"
                aria-label="Просмотр скриншота оплаты"
                @click.self="showPreview = false"
            >
                <button
                    type="button"
                    class="payment-proof-modal__close"
                    aria-label="Закрыть"
                    @click="showPreview = false"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">close</span>
                </button>
                <img :src="proof.url" :alt="proof.filename" class="payment-proof-modal__image" />
            </div>
        </Teleport>
    </article>
</template>
