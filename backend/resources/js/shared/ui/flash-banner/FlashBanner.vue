<script setup>
import { onMounted, onUnmounted, ref } from 'vue';

const props = defineProps({
    message: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'success',
        validator: (value) => ['success', 'error'].includes(value),
    },
    autoHideMs: {
        type: Number,
        default: 0,
    },
});

const visible = ref(true);
let hideTimer = null;

onMounted(() => {
    if (props.autoHideMs > 0) {
        hideTimer = window.setTimeout(() => {
            visible.value = false;
        }, props.autoHideMs);
    }
});

onUnmounted(() => {
    if (hideTimer !== null) {
        window.clearTimeout(hideTimer);
    }
});
</script>

<template>
    <div
        v-if="visible"
        class="card mb-4 border transition-opacity duration-300"
        :class="tone === 'success' ? 'border-accent/30 text-accent' : 'border-error/30 text-error'"
    >
        {{ message }}
    </div>
</template>
