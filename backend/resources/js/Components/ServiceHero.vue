<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';

const props = defineProps({
    company: {
        type: Object,
        default: null,
    },
});

const data = computed(() => props.company ?? usePage().props.companyHero ?? usePage().props.company ?? {});
</script>

<template>
    <section class="card mb-stack-element overflow-hidden">
        <div class="flex items-start gap-3">
            <AppLogo />

            <div class="min-w-0 flex-1">
                <h2 class="text-headline-md text-on-surface">{{ data.name }}</h2>
                <p class="mt-1 text-body-sm font-semibold text-accent">{{ data.tagline }}</p>
            </div>
        </div>

        <p class="mt-4 text-body-sm leading-relaxed text-text-muted">
            {{ data.home_intro }}
        </p>

        <p v-if="data.legal_name" class="mt-3 text-xs text-text-dim">
            Оператор: {{ data.legal_name }}, БИН {{ data.bin }}
        </p>
    </section>
</template>
