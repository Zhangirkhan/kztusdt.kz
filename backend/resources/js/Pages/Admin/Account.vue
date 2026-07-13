<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Head, Link } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';
import { useI18n } from 'vue-i18n';

defineProps({
    profile: Object,
    roles: Array,
});

const { t } = useI18n();
</script>

<template>
    <Head :title="t('admin.account.title')" />

    <AdminLayout>
        <template #title>{{ t('admin.account.title') }}</template>

        <AdminPage>
            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.account.cards.employee')" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item :label="t('admin.account.labels.name')">{{ profile.name || t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item :label="t('admin.account.labels.phone')">{{ profile.phone || t('admin.shared.empty') }}</a-descriptions-item>
                            <a-descriptions-item v-if="profile.email" :label="t('admin.account.labels.email')">{{ profile.email }}</a-descriptions-item>
                            <a-descriptions-item v-if="roles.length" :label="t('admin.account.labels.roles')">{{ roles.join(', ') }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card :title="t('admin.account.cards.settings')" size="small">
                        <a-typography-text type="secondary" class="admin-ant-block">{{ t('admin.account.labels.interfaceLanguage') }}</a-typography-text>
                        <LocaleSwitcher show-label class="admin-ant-block" />

                        <a-space direction="vertical" style="width: 100%">
                            <Link v-if="$page.props.auth.canAccessPwa" :href="localizedPath('/profile')">
                                <a-button block>{{ t('admin.account.actions.fullProfileInApp') }}</a-button>
                            </Link>
                            <Link :href="route('logout')" method="post" as="button">
                                <a-button block danger>{{ t('admin.account.actions.logout') }}</a-button>
                            </Link>
                        </a-space>
                    </a-card>
                </a-col>
            </a-row>
        </AdminPage>
    </AdminLayout>
</template>
