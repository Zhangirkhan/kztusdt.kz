<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { localizedPathFor } from '@/utils/localizedPath';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    href: {
        type: String,
        default: '/profile',
    },
    label: {
        type: String,
        default: '',
    },
});

const page = usePage();
const { t } = useI18n();

const backHref = computed(() => localizedPathFor(page.props.locale?.current ?? 'ru', props.href));
const backLabel = computed(() => props.label || t('subpageBack.defaultLabel'));
</script>

<template>
    <Link :href="backHref" class="subpage-back">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        {{ backLabel }}
    </Link>
</template>
