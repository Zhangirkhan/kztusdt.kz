<script setup>
defineProps({
    alt: {
        type: String,
        default: 'kztusdt.kz',
    },
});
</script>

<template>
    <img
        src="/logo.png?v=2"
        :alt="alt"
        class="object-contain"
        width="512"
        height="512"
        decoding="async"
    />
</template>
