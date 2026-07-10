<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'full',
    },
    company: {
        type: Object,
        default: null,
    },
});

const data = computed(() => props.company ?? usePage().props.companyIntro ?? usePage().props.company ?? {});
const isCompact = computed(() => props.variant === 'compact');
</script>

<template>
    <section
        class="card"
        :class="isCompact ? 'border border-outline-variant/40 bg-surface-container-low/80' : 'overflow-hidden'"
    >
        <div class="flex items-start gap-3">
            <AppLogo />

            <div class="min-w-0 flex-1">
                <p class="text-label-caps uppercase text-text-dim">О сервисе</p>
                <h2 class="mt-1 text-headline-md text-on-surface">{{ data.name }}</h2>
                <p class="mt-1 text-body-sm font-semibold text-accent">{{ data.tagline }}</p>
            </div>
        </div>

        <p class="mt-4 text-body-sm leading-relaxed text-text-muted">
            {{ data.description }}
        </p>

        <ul class="mt-4 space-y-2">
            <li
                v-for="feature in data.features"
                :key="feature"
                class="flex items-start gap-2 text-body-sm text-text-muted"
            >
                <span class="material-symbols-outlined mt-0.5 text-base text-accent">check_circle</span>
                <span>{{ feature }}</span>
            </li>
        </ul>

        <div
            v-if="data.legal_name || data.bin || data.support_email"
            class="mt-4 border-t border-outline-variant/30 pt-4 text-body-sm text-text-dim"
        >
            <p v-if="data.legal_name">{{ data.legal_name }}</p>
            <p v-if="data.bin" class="mt-1">БИН {{ data.bin }}</p>
            <p v-if="data.director" class="mt-1">Директор: {{ data.director }}</p>
            <p v-if="data.support_email" class="mt-1">
                Поддержка:
                <a :href="`mailto:${data.support_email}`" class="text-accent hover:underline">
                    {{ data.support_email }}
                </a>
            </p>
        </div>
    </section>
</template>
