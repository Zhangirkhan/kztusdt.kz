<script setup>
import SeoHead from '@/shared/ui/seo-head/SeoHead.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import AdminPwaInstallBanner from '@/widgets/admin-shell/ui/AdminPwaInstallBanner.vue';
import AdminSidebarPanel from '@/widgets/admin-shell/ui/AdminSidebarPanel.vue';
import { useAdminBreakpoint } from '@/composables/useAdminBreakpoint';
import { antLocale, registerAntDesign } from '@/plugins/antd';
import 'ant-design-vue/dist/reset.css';
import { MenuFoldOutlined, MenuUnfoldOutlined } from '@ant-design/icons-vue';
import { usePage } from '@inertiajs/vue3';
import { Button, ConfigProvider, Drawer, Layout, Space, Tag, Typography } from 'ant-design-vue';
import { computed, getCurrentInstance, onMounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const app = getCurrentInstance().appContext.app;
if (!app.config.globalProperties.__antdRegistered) {
    registerAntDesign(app);
    app.config.globalProperties.__antdRegistered = true;
}

const { Header, Sider, Content } = Layout;
const { Text } = Typography;

const COLLAPSED_STORAGE_KEY = 'admin_sider_collapsed';

const page = usePage();
const { t } = useI18n();
const { isMobile } = useAdminBreakpoint('shell');
const userName = computed(() => page.props.auth?.user?.name ?? page.props.auth?.user?.email ?? 'Admin');

const collapsed = ref(false);
const mobileMenuOpen = ref(false);

function readCollapsedPreference() {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.localStorage.getItem(COLLAPSED_STORAGE_KEY) === '1';
}

function persistCollapsedPreference(value) {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(COLLAPSED_STORAGE_KEY, value ? '1' : '0');
}

function toggleCollapsed() {
    collapsed.value = ! collapsed.value;
}

onMounted(() => {
    collapsed.value = readCollapsedPreference();
});

watch(isMobile, (mobile) => {
    if (! mobile) {
        mobileMenuOpen.value = false;
    }
});

watch(collapsed, (value) => {
    if (! isMobile.value) {
        persistCollapsedPreference(value);
    }
});

watch(() => page.url, () => {
    mobileMenuOpen.value = false;
});
</script>

<template>
    <SeoHead />

    <ConfigProvider
        :locale="antLocale"
        :theme="{
            token: {
                colorPrimary: '#1677ff',
                borderRadius: 6,
                fontFamily: 'Manrope, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif',
            },
        }"
    >
        <Layout class="admin-ant-layout">
            <Sider
                v-if="!isMobile"
                v-model:collapsed="collapsed"
                :width="240"
                :collapsed-width="72"
                theme="dark"
                class="admin-ant-sider"
                collapsible
                :trigger="null"
            >
                <AdminSidebarPanel
                    :collapsed="collapsed"
                    :show-logo-text="!collapsed"
                    :inline-collapsed="collapsed"
                />
            </Sider>

            <Drawer
                v-model:open="mobileMenuOpen"
                placement="left"
                :width="280"
                :closable="true"
                class="admin-ant-drawer"
                root-class-name="admin-ant-drawer-root"
                :body-style="{ padding: 0, background: '#001529' }"
                :header-style="{ display: 'none' }"
            >
                <AdminSidebarPanel show-logo-text />
            </Drawer>

            <Layout class="admin-ant-main">
                <Header class="admin-ant-header">
                    <div class="admin-ant-header__start">
                        <Button
                            type="text"
                            class="admin-ant-menu-trigger"
                            :aria-label="isMobile ? t('admin.shell.layout.menuAria.open') : (collapsed ? t('admin.shell.layout.menuAria.expand') : t('admin.shell.layout.menuAria.collapse'))"
                            @click="isMobile ? (mobileMenuOpen = true) : toggleCollapsed()"
                        >
                            <MenuUnfoldOutlined v-if="isMobile || collapsed" />
                            <MenuFoldOutlined v-else />
                        </Button>

                        <div class="admin-ant-header__title">
                            <slot name="title">{{ t('admin.shell.layout.defaultTitle') }}</slot>
                        </div>
                    </div>

                    <Space :size="12" class="admin-ant-header__meta">
                        <LocaleSwitcher compact code-only class="admin-ant-locale-switcher" />
                        <Tag color="success" class="admin-ant-live-tag">Live</Tag>
                        <Text type="secondary" class="admin-ant-user-name">{{ userName }}</Text>
                    </Space>
                </Header>

                <Content class="admin-ant-content" :class="{ 'admin-ant-content--mobile': isMobile }">
                    <slot />
                </Content>
            </Layout>
        </Layout>

        <AdminPwaInstallBanner />
    </ConfigProvider>
</template>

<style scoped>
.admin-ant-layout {
    min-height: 100dvh;
    min-height: 100svh;
}

.admin-ant-sider {
    position: sticky;
    top: 0;
    height: 100dvh;
    overflow: auto;
    background: #001529 !important;
    z-index: 20;
    padding-top: env(safe-area-inset-top);
}

.admin-ant-sider :deep(.ant-layout-sider-children) {
    display: flex;
    flex-direction: column;
    height: 100%;
}

.admin-ant-main {
    min-width: 0;
}

.admin-ant-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    height: calc(64px + env(safe-area-inset-top));
    padding: env(safe-area-inset-top) 16px 0;
    background: #fff;
    border-bottom: 1px solid #f0f0f0;
    line-height: 1.2;
    position: sticky;
    top: 0;
    z-index: 10;
}

.admin-ant-header__start {
    display: flex;
    align-items: center;
    gap: 8px;
    min-width: 0;
    flex: 1;
}

.admin-ant-menu-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    font-size: 18px;
    flex-shrink: 0;
}

.admin-ant-header__title {
    font-size: 20px;
    font-weight: 600;
    color: rgba(0, 0, 0, 0.88);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-ant-header__meta {
    flex-shrink: 0;
}

.admin-ant-user-name {
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-ant-content {
    margin: 16px;
    min-height: calc(100dvh - 64px - 32px - env(safe-area-inset-top));
    padding-bottom: env(safe-area-inset-bottom);
}

.admin-ant-page {
    display: flex;
    flex-direction: column;
    gap: 0;
}

.admin-ant-block {
    margin-bottom: 16px;
}

.admin-ant-card {
    margin-bottom: 16px;
}

.admin-ant-meta {
    font-size: 12px;
    color: rgba(0, 0, 0, 0.45);
}

.admin-ant-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

@media (min-width: 768px) {
    .admin-ant-header {
        padding-inline: 24px;
    }

    .admin-ant-content {
        margin: 24px;
        min-height: calc(100dvh - 64px - 48px - env(safe-area-inset-top));
    }
}

@media (max-width: 575px) {
    .admin-ant-header {
        height: calc(56px + env(safe-area-inset-top));
        padding-inline: 12px;
        gap: 8px;
    }

    .admin-ant-header__title {
        font-size: 16px;
    }

    .admin-ant-live-tag,
    .admin-ant-user-name {
        display: none;
    }

    .admin-ant-locale-switcher :deep(.inline-flex) {
        max-width: 72px;
    }

    .admin-ant-content {
        margin: 8px;
        min-height: calc(100dvh - 56px - 16px - env(safe-area-inset-top));
    }
}

.admin-ant-content--mobile {
    padding-bottom: calc(16px + env(safe-area-inset-bottom) + var(--admin-pwa-banner-offset, 0px));
}
</style>

<style>
.admin-ant-drawer-root .ant-drawer-content {
    background: #001529;
}

.admin-ant-drawer-root .ant-drawer-body {
    padding: 0;
    height: 100%;
    padding-top: env(safe-area-inset-top);
    padding-bottom: env(safe-area-inset-bottom);
}

.admin-ant-drawer-root .admin-sidebar-panel {
    min-height: 100%;
}

.admin-ant-sticky-actions {
    position: fixed;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 5;
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin: 0;
    padding: 12px 16px calc(12px + env(safe-area-inset-bottom) + var(--admin-pwa-banner-offset, 0px));
    background: rgba(255, 255, 255, 0.96);
    border-top: 1px solid #f0f0f0;
    backdrop-filter: blur(8px);
}

.admin-ant-sticky-actions .ant-btn {
    width: 100%;
    min-height: 44px;
}

@media (min-width: 768px) {
    .admin-ant-sticky-actions {
        position: static;
        flex-direction: row;
        flex-wrap: wrap;
        margin: 0 0 16px;
        padding: 0;
        background: transparent;
        border-top: none;
        backdrop-filter: none;
    }

    .admin-ant-sticky-actions .ant-btn {
        width: auto;
        min-height: 36px;
    }
}

@media (max-width: 991px) {
    .admin-ant-content .ant-card-head {
        min-height: auto;
        padding: 12px 16px;
    }

    .admin-ant-content .ant-card-body {
        padding: 12px;
    }

    .admin-ant-content .ant-descriptions-item-label,
    .admin-ant-content .ant-descriptions-item-content {
        padding: 8px 12px !important;
    }

    .admin-ant-content .ant-space {
        flex-wrap: wrap;
    }

    .admin-ant-content .ant-btn {
        min-height: 36px;
    }

    .admin-filters-select {
        width: 100%;
    }

    .admin-ant-content .ant-modal {
        max-width: calc(100vw - 32px) !important;
        margin: 16px auto;
    }
}
</style>
