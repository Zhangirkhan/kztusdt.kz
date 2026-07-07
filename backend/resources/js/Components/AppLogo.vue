<script setup>
import AppLogo from '@/shared/ui/app-logo/AppLogo.vue';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <AppLogo v-bind="$attrs" />
</template>
