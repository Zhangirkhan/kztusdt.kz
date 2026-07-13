<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

defineProps({
    company: {
        type: Object,
        default: () => ({
            name: 'kztusdt.kz',
            tagline: 'Crypto exchange USDT / KZT',
        }),
    },
});
</script>

<template>
    <Head :title="company.name" />

    <div class="app-frame">
        <main class="app-shell page-enter flex min-h-dvh flex-col px-margin-page">
            <header class="relative z-30 flex shrink-0 items-center justify-between gap-3 pt-4" style="padding-top: calc(16px + var(--safe-top))">
                <AppLogo show-wordmark class="landing-header-logo min-w-0 flex-1 overflow-hidden" />
                <LocaleSwitcher class="relative z-30 shrink-0" compact code-only />
            </header>

            <section class="flex flex-1 flex-col items-center justify-center py-stack-section text-center">
                <p class="mb-2 -mt-16 text-headline-md font-semibold leading-snug text-on-surface">
                    {{ t('landing.tagline') }}
                </p>

                <img
                    src="/logo-wordmark.png?v=2"
                    :alt="company.name"
                    class="mt-12 h-auto w-full max-w-[280px]"
                />

                <Link :href="route('auth.phone')" class="btn-primary mt-stack-section no-underline">
                    {{ t('landing.signIn') }}
                </Link>
            </section>

            <footer class="pb-6 text-center text-body-sm text-text-dim">
                <nav class="flex flex-wrap items-center justify-center gap-x-4 gap-y-2">
                    <Link :href="route('legal.show', 'terms')" class="text-accent hover:underline">
                        {{ t('landing.terms') }}
                    </Link>
                    <Link :href="route('legal.show', 'privacy')" class="text-accent hover:underline">
                        {{ t('landing.privacy') }}
                    </Link>
                </nav>
            </footer>
        </main>
    </div>
</template>
