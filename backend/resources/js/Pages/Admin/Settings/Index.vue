<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    settings: Object,
    note: String,
});
</script>

<template>
    <Head title="Настройки" />

    <AdminLayout>
        <template #title>Настройки</template>

        <AdminPage>
            <a-typography-text type="secondary" class="admin-ant-block">{{ note }}</a-typography-text>

            <a-row :gutter="[16, 16]">
                <a-col :xs="24" :lg="12">
                    <a-card title="Комиссии" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Стандартная">{{ settings.fees.default }}%</a-descriptions-item>
                            <a-descriptions-item label="Подписка">{{ settings.fees.subscription }}%</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Лимиты" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Мин. покупка">{{ settings.limits.min_buy_kzt }} ₸</a-descriptions-item>
                            <a-descriptions-item label="Макс. покупка">{{ settings.limits.max_buy_kzt }} ₸</a-descriptions-item>
                            <a-descriptions-item label="Мин. продажа">{{ settings.limits.min_sell_usdt }} USDT</a-descriptions-item>
                            <a-descriptions-item label="Макс. продажа">{{ settings.limits.max_sell_usdt }} USDT</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Курс" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Покупка">{{ settings.rate.buy }} ₸</a-descriptions-item>
                            <a-descriptions-item label="Продажа">{{ settings.rate.sell }} ₸</a-descriptions-item>
                            <a-descriptions-item label="Markup buy">{{ settings.rate.markup_buy }}</a-descriptions-item>
                            <a-descriptions-item label="Markup sell">{{ settings.rate.markup_sell }}</a-descriptions-item>
                            <a-descriptions-item label="Fallback">{{ settings.rate.fallback }}</a-descriptions-item>
                            <a-descriptions-item label="Stale">{{ settings.rate.stale ? 'Да' : 'Нет' }}</a-descriptions-item>
                            <a-descriptions-item label="Обновлён">{{ settings.rate.updated_at || '—' }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>

                <a-col :xs="24" :lg="12">
                    <a-card title="Функции" size="small">
                        <a-descriptions :column="1" size="small">
                            <a-descriptions-item label="Выводы">
                                <a-tag :color="settings.features.withdrawals_enabled ? 'success' : 'default'">
                                    {{ settings.features.withdrawals_enabled ? 'Включены' : 'Выключены' }}
                                </a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item label="Sweep">
                                <a-tag :color="settings.features.sweep_enabled ? 'success' : 'default'">
                                    {{ settings.features.sweep_enabled ? 'Включён' : 'Выключен' }}
                                </a-tag>
                            </a-descriptions-item>
                            <a-descriptions-item label="KYC provider">{{ settings.features.kyc_provider }}</a-descriptions-item>
                        </a-descriptions>
                    </a-card>
                </a-col>
            </a-row>
        </AdminPage>
    </AdminLayout>
</template>
