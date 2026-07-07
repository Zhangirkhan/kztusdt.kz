<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';

const props = defineProps({
    size: {
        type: [Number, String],
        default: 48,
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
const fontSize = computed(() => {
    const n = typeof props.size === 'number' ? props.size : parseInt(props.size, 10) || 48;

    return `${Math.round(n * 0.45)}px`;
});
const letter = computed(() => companyName.value.charAt(0).toUpperCase());
</script>

<template>
    <div class="inline-flex items-center gap-3" :class="showWordmark ? '' : 'justify-center'">
        <div
            class="brand-logo shrink-0 select-none"
            :style="{ width: pixelSize, height: pixelSize, fontSize }"
            aria-hidden="true"
        >
            {{ letter }}
        </div>

        <span v-if="showWordmark" class="text-headline-md font-bold tracking-tight text-on-surface">
            {{ companyName }}
        </span>
    </div>
</template>
