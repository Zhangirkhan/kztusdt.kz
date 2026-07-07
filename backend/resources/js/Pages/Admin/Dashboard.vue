<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import AdminStatsRow from '@/shared/ui/admin/AdminStatsRow.vue';
import { Head, Link } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';
import { computed } from 'vue';

const props = defineProps({
    stats: Object,
    services: {
        type: Object,
        default: () => ({}),
    },
});

const statItems = computed(() => [
    { label: 'Пользователи', value: props.stats.users_total, color: '#1677ff', hint: 'Всего в системе' },
    { label: 'KYC на проверке', value: props.stats.kyc_pending, color: '#faad14', hint: 'Требуют решения' },
    { label: 'KYC одобрено', value: props.stats.kyc_approved, color: '#52c41a', hint: 'Верифицированные клиенты' },
]);
</script>

<template>
    <Head title="Admin" />

    <AdminLayout>
        <template #title>Дашборд</template>

        <AdminPage>
            <AdminStatsRow :items="statItems" />

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card title="Обзор" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Пользователей">{{ stats.users_total }}</a-descriptions-item>
                            <a-descriptions-item label="KYC на проверке">
                                <a-typography-text strong type="warning">{{ stats.kyc_pending }}</a-typography-text>
                            </a-descriptions-item>
                            <a-descriptions-item label="KYC одобрено">
                                <a-typography-text strong type="success">{{ stats.kyc_approved }}</a-typography-text>
                            </a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Сервисы" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="NCANode (ЭЦП)">
                                <template v-if="services.ncanode?.enabled">
                                    <a-tag :color="services.ncanode.healthy ? 'success' : 'error'">
                                        {{ services.ncanode.healthy ? 'Доступен' : 'Недоступен' }}
                                    </a-tag>
                                    <span v-if="services.ncanode.skip_verification" class="admin-ant-meta"> (skip verification)</span>
                                </template>
                                <a-tag v-else color="default">Отключён</a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item v-if="services.ncanode?.enabled" label="URL">
                                <span class="admin-ant-meta">{{ services.ncanode.url }}</span>
                            </a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Быстрые действия" size="small">
                        <a-space direction="vertical" style="width: 100%">
                            <Link v-if="$page.props.adminNav?.sections?.kyc" href="/admin/kyc">
                                <a-button block>Проверить KYC ({{ stats.kyc_pending }})</a-button>
                            </Link>
                            <Link v-if="$page.props.adminNav?.sections?.orders" href="/admin/orders">
                                <a-button block>Заявки обмена</a-button>
                            </Link>
                            <Link v-if="$page.props.adminNav?.sections?.withdrawals" href="/admin/withdrawals">
                                <a-button block>Выводы USDT</a-button>
                            </Link>
                            <Link v-if="$page.props.adminNav?.sections?.wallets" href="/admin/wallets">
                                <a-button block>Кошельки и депозиты</a-button>
                            </Link>
                            <Link v-if="$page.props.auth.canAccessPwa" :href="localizedPath('/wallet')">
                                <a-button block type="dashed">Открыть приложение</a-button>
                            </Link>
                        </a-space>
                    </a-card>
                </a-col>
            </a-row>
        </AdminPage>
    </AdminLayout>
</template>
