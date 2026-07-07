<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: {
        type: String,
        required: true,
    },
    options: {
        type: Array,
        required: true,
    },
    size: {
        type: String,
        default: 'middle',
    },
});

const emit = defineEmits(['update:modelValue', 'change']);

const hasCounts = computed(() => props.options.some((option) => option.count !== undefined && option.count !== null));

function onChange(value) {
    emit('update:modelValue', value);
    emit('change', value);
}

function badgeStyle(value) {
    if (props.modelValue === value) {
        return {
            backgroundColor: '#fff',
            color: '#1677ff',
            boxShadow: '0 0 0 1px #1677ff inset',
        };
    }

    return {
        backgroundColor: '#f0f0f0',
        color: '#595959',
    };
}
</script>

<template>
    <a-segmented
        :value="modelValue"
        :options="options"
        :size="size"
        class="admin-ant-block admin-filters-segmented"
        @change="onChange"
    >
        <template v-if="hasCounts" #label="{ value, payload }">
            <span class="admin-filter-segment-label">
                {{ payload?.label ?? value }}
                <a-badge
                    :count="payload?.count ?? 0"
                    :number-style="badgeStyle(value)"
                    :show-zero="true"
                />
            </span>
        </template>
    </a-segmented>
</template>
