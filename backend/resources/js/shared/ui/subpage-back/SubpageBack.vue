<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { localizedPath } from '@/utils/localizedPath';

const props = defineProps({
    href: {
        type: String,
        default: '/profile',
    },
    label: {
        type: String,
        default: 'Профиль',
    },
});

const backHref = computed(() => localizedPath(props.href));
</script>

<template>
    <Link :href="backHref" class="subpage-back">
        <span class="material-symbols-outlined text-lg">arrow_back</span>
        {{ label }}
    </Link>
</template>
