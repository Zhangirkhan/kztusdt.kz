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

const page = usePage();
const companyName = computed(() => page.props.company?.name ?? 'kztusdt.kz');
const isAdminSurface = computed(() => Boolean(page.props.adminApp?.isSubdomain));
const logoSrc = computed(() => {
    if (isAdminSurface.value) {
        return '/icons/admin/logo.png?v=1';
    }

    return '/logo-mark.png?v=1';
});
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
            :src="logoSrc"
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
