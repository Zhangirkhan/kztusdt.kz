<script setup>
import { computed } from 'vue';
import { icons } from './icons';

const props = defineProps({
    name: {
        type: String,
        required: true,
    },
    size: {
        type: [Number, String],
        default: 24,
    },
    strokeWidth: {
        type: [Number, String],
        default: 1.8,
    },
});

const shapes = computed(() => icons[props.name] ?? []);
const pixelSize = computed(() => (typeof props.size === 'number' ? `${props.size}px` : props.size));
</script>

<template>
    <svg
        class="app-icon"
        :width="pixelSize"
        :height="pixelSize"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        :stroke-width="strokeWidth"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <g v-for="(shape, index) in shapes" :key="`${props.name}-${index}`">
            <path v-if="shape.type === 'path'" :d="shape.d" />
            <circle
                v-else-if="shape.type === 'circle'"
                :cx="shape.cx"
                :cy="shape.cy"
                :r="shape.r"
            />
            <rect
                v-else-if="shape.type === 'rect'"
                :x="shape.x"
                :y="shape.y"
                :width="shape.width"
                :height="shape.height"
                :rx="shape.rx"
                :ry="shape.ry"
            />
        </g>
    </svg>
</template>
