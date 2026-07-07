<script setup>
import SubpageBack from '@/shared/ui/subpage-back/SubpageBack.vue';

defineProps({
    backHref: {
        type: String,
        default: '/profile',
    },
    backLabel: {
        type: String,
        default: 'Профиль',
    },
});
</script>

<template>
    <SubpageBack :href="backHref" :label="backLabel" />
    <slot />
</template>
