<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    length: {
        type: Number,
        default: 4,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['update:modelValue', 'complete']);

const filled = computed(() => props.modelValue.length);

const keys = [
    ['1', '2', '3'],
    ['4', '5', '6'],
    ['7', '8', '9'],
    ['', '0', 'back'],
];

function press(key) {
    if (props.disabled) {
        return;
    }

    if (key === 'back') {
        emit('update:modelValue', props.modelValue.slice(0, -1));

        return;
    }

    if (!key || props.modelValue.length >= props.length) {
        return;
    }

    const next = `${props.modelValue}${key}`;
    emit('update:modelValue', next);

    if (next.length === props.length) {
        emit('complete', next);
    }
}
</script>

<template>
    <div class="pin-pad">
        <div class="pin-pad__dots" :aria-label="`${filled}/${length}`">
            <span
                v-for="index in length"
                :key="index"
                class="pin-pad__dot"
                :class="{ 'pin-pad__dot--filled': index <= filled }"
            />
        </div>

        <div class="pin-pad__grid">
            <template v-for="(row, rowIndex) in keys" :key="rowIndex">
                <button
                    v-for="(key, keyIndex) in row"
                    :key="`${rowIndex}-${keyIndex}`"
                    type="button"
                    class="pin-pad__key"
                    :class="{
                        'pin-pad__key--ghost': key === '',
                        'pin-pad__key--action': key === 'back',
                    }"
                    :disabled="disabled || key === ''"
                    :aria-label="key === 'back' ? 'Backspace' : key"
                    @click="press(key)"
                >
                    <span v-if="key === 'back'" class="material-symbols-outlined">backspace</span>
                    <span v-else>{{ key }}</span>
                </button>
            </template>
        </div>
    </div>
</template>

<style scoped>
.pin-pad__dots {
    display: flex;
    justify-content: center;
    gap: 14px;
    margin-bottom: 28px;
}

.pin-pad__dot {
    width: 14px;
    height: 14px;
    border-radius: 999px;
    border: 2px solid rgba(148, 163, 184, 0.8);
    transition: background-color 0.15s ease, border-color 0.15s ease;
}

.pin-pad__dot--filled {
    background: var(--color-accent, #2563eb);
    border-color: var(--color-accent, #2563eb);
}

.pin-pad__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    max-width: 280px;
    margin: 0 auto;
}

.pin-pad__key {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 58px;
    border: none;
    border-radius: 16px;
    background: rgba(148, 163, 184, 0.12);
    color: inherit;
    font-size: 24px;
    font-weight: 600;
    cursor: pointer;
}

.pin-pad__key:disabled {
    cursor: default;
    opacity: 0.35;
}

.pin-pad__key:not(:disabled):active {
    transform: scale(0.97);
}

.pin-pad__key--ghost {
    background: transparent;
}

.pin-pad__key--action {
    font-size: 20px;
}
</style>
