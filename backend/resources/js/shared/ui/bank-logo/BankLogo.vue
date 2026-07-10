<script setup>
import { bankLogoSrc, bankShort, bankTone } from '@/shared/lib/banks';

defineProps({
    code: {
        type: String,
        required: true,
    },
    size: {
        type: String,
        default: 'md', // sm | md
    },
});
</script>

<template>
    <span
        class="bank-logo"
        :class="size === 'xs' ? 'bank-logo--xs' : size === 'sm' ? 'bank-logo--sm' : 'bank-logo--md'"
        :data-tone="bankTone(code)"
        aria-hidden="true"
    >
        <img
            v-if="bankLogoSrc(code)"
            class="bank-logo__img"
            :src="bankLogoSrc(code)"
            :alt="code"
            loading="lazy"
            decoding="async"
        />
        <span v-else class="bank-logo__fallback">{{ bankShort(code) }}</span>
    </span>
</template>
