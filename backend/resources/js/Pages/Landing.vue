<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import AppLogo from '@/Components/AppLogo.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { useI18n } from 'vue-i18n';

defineProps({
    company: {
        type: Object,
        default: () => ({
            name: 'kztusdt.kz',
            tagline: 'Crypto exchange USDT / KZT',
        }),
    },
});

const { t } = useI18n();
const page = usePage();
const continueHref = computed(() => (
    page.props.auth?.user ? route('home') : route('auth.phone')
));
</script>

<template>
    <Head :title="company.name" />

    <div class="app-frame landing-frame">
        <main class="landing-page app-shell page-enter flex min-h-dvh flex-col">
            <div class="landing-inner mx-auto flex w-full max-w-2xl flex-1 flex-col">
                <header class="landing-header relative z-30 flex w-full min-w-0 shrink-0 items-center gap-2">
                    <AppLogo
                        size="clamp(2.25rem, 10vw, 3.25rem)"
                        show-wordmark
                        class="landing-header-logo min-w-0 flex-1"
                    />
                    <LocaleSwitcher class="landing-locale relative z-30 shrink-0" compact code-only />
                </header>

                <section class="landing-hero flex flex-1 flex-col items-center text-center">
                    <h1 class="landing-title text-headline-md font-semibold leading-snug text-on-surface">
                        {{ t('landing.tagline') }}
                    </h1>
                    <p class="landing-subtitle text-body-sm leading-snug text-on-surface-variant sm:text-body-lg">
                        {{ t('landing.subtitle') }}
                    </p>

                    <img
                        src="/logo-hero.png?v=2"
                        :alt="company.name"
                        class="landing-mark"
                        width="512"
                        height="512"
                        decoding="async"
                    />

                    <div class="landing-actions">
                        <Link :href="continueHref" class="btn-primary landing-cta no-underline">
                            {{ t('landing.signIn') }}
                        </Link>
                        <p class="landing-trust">
                            {{ t('landing.trust') }}
                        </p>
                    </div>
                </section>

                <footer class="landing-footer mt-auto text-center">
                    <nav class="landing-footer-nav">
                        <Link :href="route('legal.show', 'terms')" class="text-accent hover:underline">
                            {{ t('landing.terms') }}
                        </Link>
                        <span aria-hidden="true" class="landing-footer-sep">·</span>
                        <Link :href="route('legal.show', 'privacy')" class="text-accent hover:underline">
                            {{ t('landing.privacy') }}
                        </Link>
                    </nav>
                </footer>
            </div>
        </main>
    </div>
</template>

<style scoped>
.landing-frame {
    background: var(--color-surface);
}

.landing-page.app-shell {
    max-width: none;
    width: 100%;
    border-left: none;
    border-right: none;
    box-shadow: none;
    background: var(--color-surface);
    min-height: 100dvh;
    overflow-x: clip;
}

.landing-inner {
    min-height: 100dvh;
    padding-inline: clamp(14px, 4vw, 24px);
    box-sizing: border-box;
}

.landing-header {
    padding-top: calc(10px + var(--safe-top));
    padding-bottom: 8px;
    gap: clamp(0.35rem, 1.5vw, 0.75rem);
}

.landing-header-logo {
    min-width: 0;
    max-width: 100%;
}

.landing-header :deep(.brand-logo) {
    flex-shrink: 0;
    transform: translateY(-2px);
}

.landing-header :deep(span) {
    flex: 0 1 auto;
    min-width: 0;
    font-size: clamp(1.05rem, 4.8vw, 1.65rem);
    line-height: 1.15;
    letter-spacing: -0.02em;
    white-space: nowrap;
}

.landing-header :deep([aria-label]) {
    gap: clamp(0.35rem, 1.5vw, 0.75rem);
}

.landing-locale {
    flex: 0 0 auto;
}

.landing-locale :deep([role='group']) {
    height: 1.85rem;
    align-items: stretch;
    border-radius: 0.5rem;
    padding: 1px;
}

.landing-locale :deep(button) {
    min-height: 100%;
    min-width: 1.7rem;
    padding: 0 0.35rem;
    font-size: 0.65rem;
    line-height: 1;
    border-radius: 0.4rem;
}

.landing-hero {
    width: 100%;
    padding-top: clamp(12px, 3vh, 28px);
    padding-bottom: 12px;
    justify-content: flex-start;
}

.landing-title {
    margin: 0;
    max-width: 100%;
    text-wrap: balance;
}

.landing-subtitle {
    margin: 10px 0 0;
    max-width: min(100%, 28rem);
    padding-inline: 4px;
}

.landing-mark {
    display: block;
    margin-top: clamp(28px, 6vh, 48px);
    width: min(48vw, 200px);
    height: auto;
    max-width: 100%;
    object-fit: contain;
    user-select: none;
    background: transparent;
}

.landing-actions {
    width: min(100%, 22rem);
    margin-top: clamp(28px, 6vh, 48px);
    display: flex;
    flex-direction: column;
    align-items: center;
}

.landing-cta {
    width: 100%;
}

.landing-trust {
    margin: 14px 0 0;
    font-size: clamp(12px, 3.2vw, 13px);
    font-weight: 500;
    line-height: 1.3;
    color: var(--color-text-body, #334155);
}

:global(html.dark) .landing-trust {
    color: #e2e8f0;
}

.landing-footer {
    margin-top: auto;
    padding-top: 12px;
    padding-bottom: calc(14px + var(--safe-bottom));
}

.landing-footer-nav {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: center;
    column-gap: 0.4rem;
    row-gap: 0.2rem;
    max-width: 100%;
    padding-inline: 4px;
    font-size: clamp(10px, 2.8vw, 12px);
    line-height: 1.35;
}

.landing-footer-sep {
    color: #64748b;
    flex-shrink: 0;
}

@media (max-width: 360px) {
    .landing-header :deep(span) {
        font-size: clamp(0.95rem, 4.2vw, 1.15rem);
    }

    .landing-mark {
        width: min(44vw, 170px);
    }
}

@media (min-width: 640px) {
    .landing-mark {
        width: min(34vw, 220px);
    }
}

@media (max-height: 720px) {
    .landing-hero {
        padding-top: 8px;
    }

    .landing-mark {
        margin-top: 20px;
        width: min(38vw, 160px);
    }

    .landing-actions {
        margin-top: 20px;
    }

    .landing-trust {
        margin-top: 10px;
    }
}
</style>
