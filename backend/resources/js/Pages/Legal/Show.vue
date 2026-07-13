<script setup>
import AppLogo from '@/Components/AppLogo.vue';
import SeoHead from '@/Components/SeoHead.vue';
import { localizedPath } from '@/utils/localizedPath';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps({
    document: Object,
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

    <div class="mx-auto min-h-screen w-full max-w-container-max bg-background px-margin-page py-stack-section pb-16">
        <header class="mb-stack-section flex items-center gap-3">
            <button
                type="button"
                class="p-2 -ml-2 text-text-dim transition hover:text-on-surface"
                :aria-label="t('common.back')"
                @click="goBack"
            >
                <span class="material-symbols-outlined">arrow_back</span>
            </button>
            <AppLogo />
        </header>

        <p class="text-label-caps uppercase text-text-dim">{{ t('legal.show.documentLabel') }}</p>
        <h1 class="mt-1 text-headline-xl">{{ document.title }}</h1>
        <p class="mt-2 text-body-sm text-text-dim">{{ t('legal.show.updatedAt', { date: document.updated_at }) }}</p>

        <article class="mt-stack-section space-y-stack-section">
            <section
                v-for="(section, index) in document.sections"
                :key="index"
                class="card"
            >
                <h2 class="text-headline-md text-on-surface">{{ section.heading }}</h2>
                <div class="mt-4 space-y-3">
                    <p
                        v-for="(paragraph, paragraphIndex) in section.paragraphs"
                        :key="paragraphIndex"
                        class="text-body-sm leading-relaxed text-text-muted"
                    >
                        {{ paragraph }}
                    </p>
                </div>
            </section>
        </article>
    </div>
</template>
