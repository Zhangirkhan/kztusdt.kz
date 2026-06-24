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
</script>

<template>
    <div class="inline-flex items-center gap-3" :class="showWordmark ? '' : 'justify-center'">
        <!-- Square brand mark (transparent PNG, reads on the dark UI) -->
        <img
            src="/logo.png"
            :alt="companyName"
            :width="size"
            :height="size"
            :style="{ height: pixelSize, width: pixelSize }"
            class="block select-none"
        />

        <!-- Brand name shown alongside the mark in wordmark contexts -->
        <span v-if="showWordmark" class="text-headline-md font-semibold tracking-tight text-on-surface">
            {{ companyName }}
        </span>
    </div>
</template>
