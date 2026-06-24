<script setup>
import AppLogo from '@/Components/AppLogo.vue';
import SeoHead from '@/Components/SeoHead.vue';
import NotificationOptIn from '@/Components/NotificationOptIn.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.url);
const companyName = computed(() => page.props.company?.name ?? 'Crypto Exchange');
</script>

<template>
    <SeoHead />

    <div class="mx-auto flex min-h-screen w-full max-w-container-max flex-col bg-background pb-24">
        <header class="sticky top-0 z-40 flex h-16 items-center justify-between bg-surface/95 px-margin-page backdrop-blur-md">
            <div class="flex items-center gap-3">
                <AppLogo :size="36" />
                <div>
                    <p class="text-label-caps uppercase text-text-dim">{{ companyName }}</p>
                    <h1 class="text-headline-md">
                        <slot name="title">Главная</slot>
                    </h1>
                </div>
            </div>
            <Link href="/profile" class="btn-icon flex h-10 w-10 items-center justify-center rounded-full bg-accent/15 text-accent">
                <span class="material-symbols-outlined">person</span>
            </Link>
        </header>

        <main class="flex-1 px-margin-page py-stack-element">
            <NotificationOptIn />
            <slot />
        </main>

        <nav class="bottom-nav">
            <div class="mx-auto flex max-w-container-max items-center justify-around px-4 py-3">
                <Link
                    href="/home"
                    class="nav-item"
                    :class="current.startsWith('/home') ? 'text-accent' : 'text-text-dim'"
                >
                    <span class="material-symbols-outlined">home</span>
                    Главная
                </Link>
                <Link
                    href="/exchange"
                    class="nav-item"
                    :class="current.startsWith('/exchange') ? 'text-accent' : 'text-text-dim'"
                >
                    <span class="material-symbols-outlined">currency_exchange</span>
                    Обмен
                </Link>
                <Link
                    href="/wallet"
                    class="nav-item"
                    :class="current.startsWith('/wallet') ? 'text-accent' : 'text-text-dim'"
                >
                    <span class="material-symbols-outlined">wallet</span>
                    Кошелёк
                </Link>
                <Link
                    href="/kyc"
                    class="nav-item"
                    :class="current.startsWith('/kyc') ? 'text-accent' : 'text-text-dim'"
                >
                    <span class="material-symbols-outlined">verified_user</span>
                    KYC
                </Link>
                <Link
                    href="/profile"
                    class="nav-item"
                    :class="current.startsWith('/profile') ? 'text-accent' : 'text-text-dim'"
                >
                    <span class="material-symbols-outlined">person</span>
                    Профиль
                </Link>
            </div>
        </nav>
    </div>
</template>
