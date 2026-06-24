<script setup>
import SeoHead from '@/Components/SeoHead.vue';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const current = computed(() => page.url);
const sections = computed(() => page.props.adminNav?.sections ?? {});
const landing = computed(() => page.props.adminNav?.landing ?? '/admin');
</script>

<template>
    <SeoHead />

    <div class="min-h-screen bg-background text-on-surface">
        <header class="border-b border-outline-variant/40 bg-surface">
            <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
                <div>
                    <p class="text-label-caps uppercase text-text-dim">Admin</p>
                    <h1 class="text-headline-md">
                        <slot name="title">Панель</slot>
                    </h1>
                </div>
                <div class="flex flex-wrap items-center justify-end gap-3 text-sm">
                    <Link
                        v-if="page.props.auth.canAccessPwa"
                        href="/home"
                        class="text-text-dim hover:text-accent"
                    >
                        PWA
                    </Link>
                    <Link
                        v-if="sections.dashboard"
                        href="/admin"
                        class="hover:text-accent"
                        :class="current === '/admin' ? 'text-accent' : 'text-text-dim'"
                    >
                        Dashboard
                    </Link>
                    <Link
                        v-if="sections.kyc"
                        href="/admin/kyc"
                        class="hover:text-accent"
                        :class="current.startsWith('/admin/kyc') ? 'text-accent' : 'text-text-dim'"
                    >
                        KYC
                    </Link>
                    <Link
                        v-if="sections.orders"
                        href="/admin/orders"
                        class="hover:text-accent"
                        :class="current.startsWith('/admin/orders') ? 'text-accent' : 'text-text-dim'"
                    >
                        Заявки
                    </Link>
                    <Link
                        v-if="sections.withdrawals"
                        href="/admin/withdrawals"
                        class="hover:text-accent"
                        :class="current.startsWith('/admin/withdrawals') ? 'text-accent' : 'text-text-dim'"
                    >
                        Выводы
                    </Link>
                    <Link
                        v-if="sections.wallets"
                        href="/admin/wallets"
                        class="hover:text-accent"
                        :class="current.startsWith('/admin/wallets') ? 'text-accent' : 'text-text-dim'"
                    >
                        Кошельки
                    </Link>
                    <Link
                        v-if="sections.sweeps"
                        href="/admin/sweeps"
                        class="hover:text-accent"
                        :class="current.startsWith('/admin/sweeps') ? 'text-accent' : 'text-text-dim'"
                    >
                        Sweeps
                    </Link>
                    <Link
                        v-if="sections.subscriptions"
                        href="/admin/subscriptions"
                        class="hover:text-accent"
                        :class="current.startsWith('/admin/subscriptions') ? 'text-accent' : 'text-text-dim'"
                    >
                        Подписки
                    </Link>

                    <div class="flex items-center gap-2 border-l border-outline-variant/40 pl-3">
                        <Link
                            href="/admin/account"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 hover:bg-surface-container-high hover:text-accent"
                            :class="current.startsWith('/admin/account') ? 'text-accent' : 'text-text-dim'"
                        >
                            <span class="material-symbols-outlined text-lg">person</span>
                            <span class="hidden sm:inline">Профиль</span>
                        </Link>
                        <Link
                            :href="route('logout')"
                            method="post"
                            as="button"
                            class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-text-dim hover:bg-surface-container-high hover:text-red-400"
                        >
                            <span class="material-symbols-outlined text-lg">logout</span>
                            <span class="hidden sm:inline">Выход</span>
                        </Link>
                    </div>
                </div>
            </div>
        </header>

        <main class="mx-auto max-w-6xl px-6 py-8">
            <slot />
        </main>
    </div>
</template>
