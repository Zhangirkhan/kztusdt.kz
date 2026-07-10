<script setup>
import AppLogo from '@/shared/ui/app-logo/AppLogo.vue';
import { buildAdminNavGroups } from '@/entities/admin/model/nav';
import { localizedPath } from '@/utils/localizedPath';
import {
    AccountBookOutlined,
    AuditOutlined,
    DashboardOutlined,
    DollarOutlined,
    ExportOutlined,
    FileProtectOutlined,
    HistoryOutlined,
    LogoutOutlined,
    MessageOutlined,
    SafetyCertificateOutlined,
    SettingOutlined,
    SyncOutlined,
    TeamOutlined,
    UserOutlined,
    WalletOutlined,
} from '@ant-design/icons-vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu, Typography } from 'ant-design-vue';
import { computed, h } from 'vue';

const props = defineProps({
    collapsed: {
        type: Boolean,
        default: false,
    },
    showLogoText: {
        type: Boolean,
        default: true,
    },
    inlineCollapsed: {
        type: Boolean,
        default: false,
    },
});

const { Text } = Typography;
const page = usePage();

const current = computed(() => page.url);
const sections = computed(() => page.props.adminNav?.sections ?? {});
const companyName = computed(() => page.props.company?.name ?? 'kztusdt.kz');

const iconMap = {
    dashboard: DashboardOutlined,
    group: TeamOutlined,
    verified_user: SafetyCertificateOutlined,
    receipt_long: AccountBookOutlined,
    account_balance: DollarOutlined,
    call_made: ExportOutlined,
    account_balance_wallet: WalletOutlined,
    settings: SettingOutlined,
    gavel: FileProtectOutlined,
    history: HistoryOutlined,
    sync: SyncOutlined,
    card_membership: AuditOutlined,
    chat: MessageOutlined,
    campaign: AuditOutlined,
};

const navGroups = computed(() => buildAdminNavGroups(sections.value));

const selectedKeys = computed(() => {
    const active = navGroups.value
        .flatMap((group) => group.items)
        .find((item) => item.match(current.value));

    return active ? [active.href] : [];
});

const menuItems = computed(() =>
    navGroups.value.map((group) => ({
        type: 'group',
        label: group.label,
        children: group.items.map((item) => ({
            key: item.href,
            icon: () => h(iconMap[item.icon] ?? DashboardOutlined),
            label: h(Link, { href: item.href }, () => item.label),
        })),
    })),
);

const footerItems = computed(() => {
    const items = [];

    if (page.props.auth.canAccessPwa) {
        items.push({
            key: 'pwa',
            icon: () => h(DashboardOutlined),
            label: h(Link, { href: localizedPath('/wallet') }, () => 'В приложение'),
        });
    }

    items.push({
        key: 'account',
        icon: () => h(UserOutlined),
        label: h(Link, { href: '/admin/account' }, () => 'Аккаунт'),
    });

    return items;
});

const footerSelectedKeys = computed(() =>
    current.value.startsWith('/admin/account') ? ['account'] : [],
);
</script>

<template>
    <div class="admin-sidebar-panel">
        <div class="admin-ant-logo" :class="{ 'admin-ant-logo--collapsed': collapsed && !showLogoText }">
            <AppLogo />
            <div v-if="showLogoText" class="admin-ant-logo__text">
                <strong>{{ companyName }}</strong>
                <Text type="secondary">Admin</Text>
            </div>
        </div>

        <Menu
            theme="dark"
            mode="inline"
            :inline-collapsed="inlineCollapsed"
            :selected-keys="selectedKeys"
            :items="menuItems"
            class="admin-ant-menu"
        />

        <div class="admin-ant-sider__footer">
            <Menu
                theme="dark"
                mode="inline"
                :inline-collapsed="inlineCollapsed"
                :selected-keys="footerSelectedKeys"
                :items="footerItems"
            />
            <Link
                :href="route('logout')"
                method="post"
                as="button"
                class="admin-ant-logout"
                :class="{ 'admin-ant-logout--collapsed': inlineCollapsed }"
            >
                <LogoutOutlined />
                <span v-if="!inlineCollapsed">Выйти</span>
            </Link>
        </div>
    </div>
</template>

<style scoped>
.admin-sidebar-panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 100%;
}

.admin-ant-logo {
    display: flex;
    align-items: center;
    gap: 10px;
    height: 64px;
    padding: 0 16px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}

.admin-ant-logo--collapsed {
    justify-content: center;
    padding: 0 12px;
}

.admin-ant-logo__text {
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.admin-ant-logo__text strong {
    color: #fff;
    font-size: 14px;
    line-height: 1.2;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.admin-ant-logo__text :deep(.ant-typography) {
    color: rgba(255, 255, 255, 0.45) !important;
    font-size: 11px;
    line-height: 1.2;
}

.admin-ant-logo :deep(.brand-logo) {
    background: transparent;
    border: none;
    flex-shrink: 0;
}

.admin-ant-menu {
    flex: 1;
    border-inline-end: none !important;
    padding: 8px 0 12px;
    overflow: auto;
}

.admin-ant-menu :deep(.ant-menu-item-group-title) {
    padding: 12px 16px 6px;
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: rgba(255, 255, 255, 0.35);
}

.admin-ant-menu :deep(.ant-menu-item-group-list) {
    margin-bottom: 4px;
}

.admin-ant-sider__footer {
    margin-top: auto;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    flex-shrink: 0;
}

.admin-ant-sider__footer :deep(.ant-menu) {
    border-inline-end: none !important;
}

.admin-ant-logout {
    display: flex;
    align-items: center;
    gap: 10px;
    width: calc(100% - 16px);
    margin: 8px;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    background: transparent;
    color: rgba(255, 255, 255, 0.65);
    font-size: 14px;
    cursor: pointer;
    text-align: left;
}

.admin-ant-logout--collapsed {
    justify-content: center;
    width: calc(100% - 16px);
    padding: 8px;
}

.admin-ant-logout:hover {
    color: #fff;
    background: rgba(255, 255, 255, 0.08);
}

.admin-ant-menu :deep(a),
.admin-ant-sider__footer :deep(a) {
    color: inherit;
    text-decoration: none;
}
</style>
