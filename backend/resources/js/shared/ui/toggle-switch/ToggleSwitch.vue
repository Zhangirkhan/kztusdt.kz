<script setup>
defineProps({
    disabled: {
        type: Boolean,
        default: false,
    },
    label: {
        type: String,
        default: '',
    },
});

const modelValue = defineModel({ type: Boolean, default: false });
</script>

<template>
    <button
        type="button"
        role="switch"
        class="toggle-switch"
        :class="{ 'toggle-switch--on': modelValue }"
        :aria-checked="modelValue"
        :aria-label="label || undefined"
        :disabled="disabled"
        @click="modelValue = !modelValue"
    >
        <span class="toggle-switch__thumb" />
    </button>
</template>
