<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    size: {
        type: [Number, String],
        default: 56,
    },
    showWordmark: {
        type: Boolean,
        default: false,
    },
});

const companyName = computed(() => usePage().props.company?.name ?? 'kztusdt.kz');
const pixelSize = computed(() =>
    typeof props.size === 'number' ? `${props.size}px` : props.size,
);
</script>

<template>
    <div
        class="inline-flex items-center"
        :class="showWordmark ? 'gap-3' : 'justify-center'"
        :aria-label="companyName"
    >
        <img
            src="/logo.png?v=2"
            alt=""
            class="brand-logo shrink-0 select-none object-contain"
            :style="{ width: pixelSize, height: pixelSize }"
            width="512"
            height="512"
            decoding="async"
        />

        <span
            v-if="showWordmark"
            class="text-headline-md font-bold tracking-tight text-on-surface"
        >
            {{ companyName }}
        </span>
    </div>
</template>
