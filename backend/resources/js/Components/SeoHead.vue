<script setup>
import SeoHead from '@/shared/ui/seo-head/SeoHead.vue';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <SeoHead v-bind="$attrs" />
</template>
