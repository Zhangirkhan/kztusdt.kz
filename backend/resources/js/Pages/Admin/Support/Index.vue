<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    totalUnread: {
        type: Number,
        default: 0,
    },
});

function formatDate(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString('ru-RU');
}

function orderLabel(conversation) {
    if (!conversation.order) {
        return 'Без заявки';
    }

    const direction = conversation.order.direction === 'buy' ? 'Покупка' : 'Продажа';

    return `Заявка №${conversation.order.id} · ${direction}`;
}
</script>

<template>
    <Head title="Чат с клиентами" />

    <AdminLayout>
        <template #title>Чат с клиентами</template>

        <AdminPage>
            <div class="admin-support-toolbar">
                <p class="admin-support-toolbar__hint">
                    Диалоги с клиентами по вопросам обмена.
                    <span v-if="totalUnread > 0">Непрочитанных: {{ totalUnread }}</span>
                </p>
            </div>

            <a-empty v-if="conversations.length === 0" description="Пока нет сообщений от клиентов" />

            <div v-else class="admin-support-list">
                <Link
                    v-for="conversation in conversations"
                    :key="conversation.id"
                    :href="route('admin.support.show', conversation.id)"
                    class="admin-support-list__item"
                >
                    <div class="admin-support-list__main">
                        <p class="admin-support-list__name">
                            {{ conversation.user.name || 'Клиент' }}
                            <a-badge v-if="conversation.unread_count > 0" :count="conversation.unread_count" />
                        </p>
                        <p class="admin-support-list__meta">
                            {{ orderLabel(conversation) }}
                            · {{ conversation.user.phone || conversation.user.email || `ID ${conversation.user.id}` }}
                        </p>
                        <p class="admin-support-list__preview">{{ conversation.last_message_preview }}</p>
                    </div>
                    <p class="admin-support-list__time">{{ formatDate(conversation.last_message_at) }}</p>
                </Link>
            </div>
        </AdminPage>
    </AdminLayout>
</template>

<style scoped>
.admin-support-toolbar {
    margin-bottom: 16px;
}

.admin-support-toolbar__hint {
    margin: 0;
    color: #64748b;
}

.admin-support-list {
    display: grid;
    gap: 12px;
}

.admin-support-list__item {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    padding: 16px;
    border-radius: 14px;
    border: 1px solid #e2e8f0;
    background: #fff;
    text-decoration: none;
    color: inherit;
    transition: border-color 0.15s, box-shadow 0.15s;
}

.admin-support-list__item:hover {
    border-color: #93c5fd;
    box-shadow: 0 8px 24px rgba(37, 99, 235, 0.08);
}

.admin-support-list__name {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 4px;
    font-weight: 700;
}

.admin-support-list__meta,
.admin-support-list__preview,
.admin-support-list__time {
    margin: 0;
    color: #64748b;
    font-size: 13px;
}

.admin-support-list__preview {
    margin-top: 8px;
    color: #0f172a;
}

.admin-support-list__time {
    flex-shrink: 0;
    white-space: nowrap;
}
</style>
