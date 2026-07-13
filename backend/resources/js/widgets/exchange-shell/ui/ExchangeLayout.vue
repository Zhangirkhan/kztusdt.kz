<script setup>
import AppLogo from '@/shared/ui/app-logo/AppLogo.vue';
import AppIcon from '@/shared/ui/icon/AppIcon.vue';
import SeoHead from '@/shared/ui/seo-head/SeoHead.vue';
import NotificationOptIn from '@/Components/NotificationOptIn.vue';
import AppLockOverlay from '@/widgets/app-lock/ui/AppLockOverlay.vue';
import { useAppLock } from '@/composables/useAppLock';
import { buildExchangeNavItems } from '@/shared/config/exchange-nav';
import { Link, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    showBrand: {
        type: Boolean,
        default: true,
    },
    hideHeader: {
        type: Boolean,
        default: false,
    },
    flushMain: {
        type: Boolean,
        default: false,
    },
});

const page = usePage();
const { t } = useI18n();
const current = computed(() => page.url);
const companyName = computed(() => page.props.company?.name ?? 'kztusdt.kz');
const canUseWallet = computed(() => page.props.auth?.user?.can_use_wallet ?? false);
const locale = computed(() => page.props.locale?.current ?? 'ru');
const isAuthenticated = computed(() => Boolean(page.props.auth?.user));
const { isLocked } = useAppLock();

const navItems = computed(() => buildExchangeNavItems(canUseWallet.value, locale.value));

const keyboardInsetPx = ref(0);
let viewportResizeHandler = null;

function updateKeyboardInset() {
    if (!window.visualViewport) {
        keyboardInsetPx.value = 0;
        return;
    }

    const vv = window.visualViewport;
    const inset = Math.max(0, window.innerHeight - vv.height - vv.offsetTop);
    keyboardInsetPx.value = Math.round(inset);
}

onMounted(() => {
    viewportResizeHandler = () => updateKeyboardInset();
    updateKeyboardInset();
    window.visualViewport?.addEventListener('resize', viewportResizeHandler);
    window.visualViewport?.addEventListener('scroll', viewportResizeHandler);
});

onUnmounted(() => {
    if (viewportResizeHandler) {
        window.visualViewport?.removeEventListener('resize', viewportResizeHandler);
        window.visualViewport?.removeEventListener('scroll', viewportResizeHandler);
    }
});
</script>

<template>
    <SeoHead />

    <div class="app-frame">
        <div
            class="app-shell page-enter"
            :class="{ 'app-shell--chat-fullscreen': hideHeader && flushMain }"
        >
            <header v-if="!hideHeader" class="page-header">
                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <AppLogo v-if="showBrand" :size="32" />
                    <div class="min-w-0">
                        <p v-if="showBrand" class="page-header__brand text-label-caps uppercase text-text-dim">{{ companyName }}</p>
                        <h1 class="page-header__title truncate" :class="showBrand ? '' : 'text-base'">
                            <slot name="title">{{ t('wallet.title') }}</slot>
                        </h1>
                    </div>
                </div>
                <div v-if="$slots['header-actions']" class="flex shrink-0 items-center gap-2">
                    <slot name="header-actions" />
                </div>
            </header>

            <main
                class="flex-1 pb-4"
                :class="flushMain ? 'px-0 pt-0' : 'px-margin-page pt-4'"
                :style="{ paddingBottom: flushMain
                    ? 'calc(var(--bottom-nav-height) + var(--safe-bottom))'
                    : 'calc(var(--bottom-nav-height) + 24px + var(--safe-bottom))' }"
            >
                <NotificationOptIn />
                <slot />
            </main>

            <nav v-if="keyboardInsetPx === 0" class="bottom-nav" :aria-label="t('nav.main')">
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
                        :aria-label="item.locked ? `${item.label} — ${t('nav.kycLockHint')}` : item.label"
                    >
                        <span class="bottom-nav__pill">
                            <AppIcon
                                :name="item.locked ? 'lock' : item.icon"
                                :size="20"
                                :stroke-width="2.2"
                            />
                        </span>
                        <span class="bottom-nav__label">{{ item.label }}</span>
                    </Link>
                </div>
            </nav>
        </div>

        <AppLockOverlay v-if="isAuthenticated && isLocked" />
    </div>
</template>
