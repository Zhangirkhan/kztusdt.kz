<script setup>
import AppLogo from '@/Components/AppLogo.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { localizedPath } from '@/utils/localizedPath';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    documents: Array,
    updatedAt: String,
});
const { t } = useI18n();

function goBack() {
    try {
        if (window.history.length > 1) {
            const referrer = document.referrer;

            if (!referrer) {
                window.history.back();
                return;
            }

            const ref = new URL(referrer);

            if (ref.origin === window.location.origin) {
                window.history.back();
                return;
            }
        }
    } catch {
        // fall through
    }

    router.visit(localizedPath('/'));
}
</script>

<template>
    <SeoHead />

    <div class="mx-auto min-h-screen w-full max-w-container-max bg-background px-margin-page py-stack-section">
        <header class="mb-stack-section flex items-center gap-3">
            <button
                type="button"
                class="p-2 -ml-2 text-text-dim transition hover:text-on-surface"
                :aria-label="t('common.back')"
                @click="goBack"
            >
                <span class="material-symbols-outlined">arrow_back</span>
            </button>
            <AppLogo show-wordmark />
        </header>

        <h1 class="text-headline-xl">{{ t('legal.index.title') }}</h1>
        <p class="mt-2 text-body-sm text-text-muted">
            {{ t('legal.index.subtitle', { date: updatedAt }) }}
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
