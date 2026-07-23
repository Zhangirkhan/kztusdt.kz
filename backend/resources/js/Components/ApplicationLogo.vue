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
        src="/logo-mark.png?v=1"
        :alt="alt"
        class="object-contain"
        width="378"
        height="378"
        decoding="async"
    />
</template>
