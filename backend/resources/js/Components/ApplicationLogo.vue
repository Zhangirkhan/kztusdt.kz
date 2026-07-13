<script setup>
import { computed } from 'vue';
import { useTheme } from '@/composables/useTheme';

defineProps({
    alt: {
        type: String,
        default: 'kztusdt.kz',
    },
});

const { isDark } = useTheme();
const logoSrc = computed(() =>
    isDark.value ? '/logo-dark.png?v=5' : '/logo.png?v=5',
);
</script>

<template>
    <img
        :src="logoSrc"
        :alt="alt"
        class="object-contain"
        width="512"
        height="512"
        decoding="async"
    />
</template>
