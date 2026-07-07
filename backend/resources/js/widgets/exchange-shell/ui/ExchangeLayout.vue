<script setup>
import AppLogo from '@/shared/ui/app-logo/AppLogo.vue';
import SeoHead from '@/shared/ui/seo-head/SeoHead.vue';
import NotificationOptIn from '@/Components/NotificationOptIn.vue';
import { buildExchangeNavItems } from '@/shared/config/exchange-nav';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.url);
const companyName = computed(() => page.props.company?.name ?? 'kztusdt.kz');
const canUseWallet = computed(() => page.props.auth?.user?.can_use_wallet ?? false);

const navItems = computed(() => buildExchangeNavItems(canUseWallet.value));
</script>

<template>
    <SeoHead />

    <div class="app-frame">
        <div class="app-shell page-enter">
            <header class="page-header">
                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <AppLogo :size="40" />
                    <div class="min-w-0">
                        <p class="text-label-caps uppercase text-text-dim">{{ companyName }}</p>
                        <h1 class="truncate text-headline-md">
                            <slot name="title">Кошелёк</slot>
                        </h1>
                    </div>
                </div>
                <Link
                    :href="route('profile.show')"
                    class="btn-icon flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-accent"
                >
                    <span class="material-symbols-outlined text-xl">person</span>
                </Link>
            </header>

            <main
                class="flex-1 px-margin-page pb-4"
                style="padding-bottom: calc(var(--bottom-nav-height) + 24px + var(--safe-bottom))"
            >
                <NotificationOptIn />
                <slot />
            </main>

            <nav class="bottom-nav" aria-label="Основная навигация">
                <div class="bottom-nav__inner">
                    <Link
                        v-for="item in navItems"
                        :key="item.label"
                        :href="item.href"
                        class="bottom-nav__item"
                        :class="{
                            'bottom-nav__item--active': item.active(current) && !item.locked,
                            'bottom-nav__item--locked': item.locked,
                        }"
                        :aria-label="item.locked ? `${item.label} — пройдите KYC` : item.label"
                    >
                        <span class="bottom-nav__pill">
                            <span class="material-symbols-outlined text-xl">
                                {{ item.locked ? 'lock' : item.icon }}
                            </span>
                        </span>
                        <span class="bottom-nav__label">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
