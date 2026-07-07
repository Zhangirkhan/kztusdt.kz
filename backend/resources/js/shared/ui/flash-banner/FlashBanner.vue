<script setup>
defineProps({
    message: {
        type: String,
        required: true,
    },
    tone: {
        type: String,
        default: 'success',
        validator: (value) => ['success', 'error'].includes(value),
    },
});
</script>

<template>
    <div
        class="card mb-4 border"
        :class="tone === 'success' ? 'border-accent/30 text-accent' : 'border-error/30 text-error'"
    >
        {{ message }}
    </div>
</template>
