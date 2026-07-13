<script setup>
import { computed, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLogo from '@/shared/ui/app-logo/AppLogo.vue';
import { useAdminPwaInstall } from '@/composables/useAdminPwaInstall';

const { t } = useI18n();
const { canInstall, showIosHint, dismiss, install } = useAdminPwaInstall();

const visible = computed(() => canInstall.value || showIosHint.value);

watch(visible, (isVisible) => {
    document.documentElement.style.setProperty(
        '--admin-pwa-banner-offset',
        isVisible ? '120px' : '0px',
    );
}, { immediate: true });
</script>

<template>
    <Transition
        enter-active-class="transition duration-300 ease-out"
        enter-from-class="translate-y-full opacity-0"
        enter-to-class="translate-y-0 opacity-100"
        leave-active-class="transition duration-200 ease-in"
        leave-from-class="translate-y-0 opacity-100"
        leave-to-class="translate-y-full opacity-0"
    >
        <div
            v-if="visible"
            class="admin-pwa-install"
        >
            <div class="admin-pwa-install__content">
                <AppLogo class="admin-pwa-install__logo" />

                <div class="admin-pwa-install__text">
                    <p class="admin-pwa-install__title">{{ t('admin.shell.pwa.title') }}</p>
                    <p v-if="canInstall" class="admin-pwa-install__hint">
                        {{ t('admin.shell.pwa.hintInstallable') }}
                    </p>
                    <p v-else class="admin-pwa-install__hint">
                        {{ t('admin.shell.pwa.hintIos') }}
                    </p>

                    <div class="admin-pwa-install__actions">
                        <button
                            v-if="canInstall"
                            type="button"
                            class="admin-pwa-install__btn admin-pwa-install__btn--primary"
                            @click="install"
                        >
                            {{ t('admin.shell.pwa.install') }}
                        </button>
                        <button
                            type="button"
                            class="admin-pwa-install__btn"
                            @click="dismiss"
                        >
                            {{ t('admin.shell.pwa.dismiss') }}
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="admin-pwa-install__close"
                    :aria-label="t('admin.shell.pwa.closeAria')"
                    @click="dismiss"
                >
                    ✕
                </button>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.admin-pwa-install {
    position: fixed;
    inset-inline: 0;
    bottom: 0;
    z-index: 1000;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom));
    background: rgba(255, 255, 255, 0.98);
    border-top: 1px solid #e2e8f0;
    box-shadow: 0 -8px 24px rgba(15, 23, 42, 0.08);
}

.admin-pwa-install__content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    max-width: 720px;
    margin: 0 auto;
}

.admin-pwa-install__logo {
    flex-shrink: 0;
}

.admin-pwa-install__text {
    min-width: 0;
    flex: 1;
}

.admin-pwa-install__title {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: #0f172a;
}

.admin-pwa-install__hint {
    margin: 4px 0 0;
    font-size: 12px;
    line-height: 1.4;
    color: #64748b;
}

.admin-pwa-install__actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 10px;
}

.admin-pwa-install__btn {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    background: #fff;
    color: #334155;
    font-size: 13px;
    font-weight: 600;
    padding: 8px 14px;
    cursor: pointer;
}

.admin-pwa-install__btn--primary {
    border-color: #2563eb;
    background: #2563eb;
    color: #fff;
}

.admin-pwa-install__close {
    border: none;
    background: transparent;
    color: #94a3b8;
    font-size: 16px;
    line-height: 1;
    padding: 4px;
    cursor: pointer;
}
</style>
