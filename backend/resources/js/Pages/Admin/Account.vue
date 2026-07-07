<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import LocaleSwitcher from '@/Components/LocaleSwitcher.vue';
import { Head, Link } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';

defineProps({
    profile: Object,
    roles: Array,
});
</script>

<template>
    <Head title="Аккаунт" />

    <AdminLayout>
        <template #title>Аккаунт</template>

        <AdminPage>
            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card title="Сотрудник" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Имя">{{ profile.name || '—' }}</a-descriptions-item>
                            <a-descriptions-item label="Телефон">{{ profile.phone || '—' }}</a-descriptions-item>
                            <a-descriptions-item v-if="profile.email" label="Email">{{ profile.email }}</a-descriptions-item>
                            <a-descriptions-item v-if="roles.length" label="Роли">{{ roles.join(', ') }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Настройки" size="small">
                        <a-typography-text type="secondary" class="admin-ant-block">Язык интерфейса</a-typography-text>
                        <LocaleSwitcher show-label class="admin-ant-block" />

                        <a-space direction="vertical" style="width: 100%">
                            <Link v-if="$page.props.auth.canAccessPwa" :href="localizedPath('/profile')">
                                <a-button block>Полный профиль в приложении</a-button>
                            </Link>
                            <Link :href="route('logout')" method="post" as="button">
                                <a-button block danger>Выйти</a-button>
                            </Link>
                        </a-space>
                    </a-card>
                </a-col>
            </a-row>
        </AdminPage>
    </AdminLayout>
</template>
