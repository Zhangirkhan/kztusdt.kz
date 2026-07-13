<script setup>
import SubpageBack from '@/shared/ui/subpage-back/SubpageBack.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    backHref: {
        type: String,
        default: '/profile',
    },
    backLabel: {
        type: String,
        default: '',
    },
});

const { t } = useI18n();
</script>

<template>
    <SubpageBack :href="backHref" :label="backLabel || t('nav.profile')" />
    <slot />
</template>
