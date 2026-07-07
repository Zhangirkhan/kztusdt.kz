<script setup>
import SeoHead from '@/shared/ui/seo-head/SeoHead.vue';
import AdminSidebarPanel from '@/widgets/admin-shell/ui/AdminSidebarPanel.vue';
import { antLocale, registerAntDesign } from '@/plugins/antd';
import 'ant-design-vue/dist/reset.css';
import { MenuFoldOutlined, MenuUnfoldOutlined } from '@ant-design/icons-vue';
import { usePage } from '@inertiajs/vue3';
import { Button, ConfigProvider, Drawer, Layout, Space, Tag, Typography } from 'ant-design-vue';
import { computed, getCurrentInstance, onMounted, onUnmounted, ref, watch } from 'vue';

const app = getCurrentInstance().appContext.app;
if (!app.config.globalProperties.__antdRegistered) {
    registerAntDesign(app);
    app.config.globalProperties.__antdRegistered = true;
}

const { Header, Sider, Content } = Layout;
const { Text } = Typography;

const COLLAPSED_STORAGE_KEY = 'admin_sider_collapsed';
const MOBILE_BREAKPOINT = 992;

const page = usePage();
const userName = computed(() => page.props.auth?.user?.name ?? page.props.auth?.user?.email ?? 'Admin');

const collapsed = ref(false);
const isMobile = ref(false);
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

function updateViewport() {
    isMobile.value = window.innerWidth < MOBILE_BREAKPOINT;

    if (! isMobile.value) {
        mobileMenuOpen.value = false;
    }
}

function toggleCollapsed() {
    collapsed.value = ! collapsed.value;
}

onMounted(() => {
    collapsed.value = readCollapsedPreference();
    updateViewport();
    window.addEventListener('resize', updateViewport);
});

onUnmounted(() => {
    window.removeEventListener('resize', updateViewport);
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
                            :aria-label="isMobile ? 'Открыть меню' : (collapsed ? 'Развернуть меню' : 'Свернуть меню')"
                            @click="isMobile ? (mobileMenuOpen = true) : toggleCollapsed()"
                        >
                            <MenuUnfoldOutlined v-if="isMobile || collapsed" />
                            <MenuFoldOutlined v-else />
                        </Button>

                        <div class="admin-ant-header__title">
                            <slot name="title">Панель</slot>
                        </div>
                    </div>

                    <Space :size="12" class="admin-ant-header__meta">
                        <Tag color="success" class="admin-ant-live-tag">Live</Tag>
                        <Text type="secondary" class="admin-ant-user-name">{{ userName }}</Text>
                    </Space>
                </Header>

                <Content class="admin-ant-content">
                    <slot />
                </Content>
            </Layout>
        </Layout>
    </ConfigProvider>
</template>

<style scoped>
.admin-ant-layout {
    min-height: 100dvh;
}

.admin-ant-sider {
    position: sticky;
    top: 0;
    height: 100dvh;
    overflow: auto;
    background: #001529 !important;
    z-index: 20;
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
    height: 64px;
    padding: 0 16px;
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
    min-height: calc(100dvh - 64px - 32px);
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
        padding: 0 24px;
    }

    .admin-ant-content {
        margin: 24px;
        min-height: calc(100dvh - 64px - 48px);
    }
}

@media (max-width: 575px) {
    .admin-ant-header__title {
        font-size: 17px;
    }

    .admin-ant-live-tag {
        display: none;
    }

    .admin-ant-user-name {
        max-width: 110px;
        font-size: 12px;
    }
}
</style>

<style>
.admin-ant-drawer-root .ant-drawer-content {
    background: #001529;
}

.admin-ant-drawer-root .ant-drawer-body {
    padding: 0;
    height: 100%;
}

.admin-ant-drawer-root .admin-sidebar-panel {
    min-height: 100dvh;
}
</style>
