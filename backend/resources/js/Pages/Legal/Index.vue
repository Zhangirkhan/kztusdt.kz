<script setup>
import AppLogo from '@/Components/AppLogo.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    documents: Array,
    updatedAt: String,
});
</script>

<template>
    <SeoHead />

    <div class="mx-auto min-h-screen w-full max-w-container-max bg-background px-margin-page py-stack-section">
        <header class="mb-stack-section flex items-center gap-3">
            <Link :href="route('auth.phone')" class="p-2 -ml-2 text-text-dim transition hover:text-on-surface">
                <span class="material-symbols-outlined">arrow_back</span>
            </Link>
            <AppLogo :size="36" show-wordmark />
        </header>

        <h1 class="text-headline-xl">Юридические документы</h1>
        <p class="mt-2 text-body-sm text-text-muted">
            Обязательные документы сервиса. Редакция от {{ updatedAt }}.
        </p>

        <div class="mt-stack-section space-y-3">
            <Link
                v-for="document in documents"
                :key="document.slug"
                :href="route('legal.show', document.slug)"
                class="card block no-underline transition hover:border-accent/30"
            >
                <p class="font-semibold text-on-surface">{{ document.title }}</p>
                <p class="mt-1 text-body-sm text-text-muted">{{ document.description }}</p>
            </Link>
        </div>
    </div>
</template>
