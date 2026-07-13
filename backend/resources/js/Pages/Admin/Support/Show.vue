<script setup>
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AdminBackLink from '@/shared/ui/admin/AdminBackLink.vue';
import AdminPage from '@/shared/ui/admin/AdminPage.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
    conversation: Object,
    messages: {
        type: Array,
        default: () => [],
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    totalUnread: {
        type: Number,
        default: 0,
    },
});

const messagesEl = ref(null);
const { t } = useI18n();

const form = useForm({
    body: '',
});

function formatDate(value) {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
}

function orderLabel(conversation) {
    if (!conversation.order) {
        return t('admin.support.index.noOrder');
    }

    const direction = conversation.order.direction === 'buy' ? t('admin.shared.direction.buy') : t('admin.shared.direction.sell');

    return t('admin.support.index.orderLabel', { id: conversation.order.id, direction });
}

function scrollToBottom() {
    nextTick(() => {
        if (messagesEl.value) {
            messagesEl.value.scrollTop = messagesEl.value.scrollHeight;
        }
    });
}

function submit() {
    form.post(route('admin.support.messages.store', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('body');
            scrollToBottom();
        },
    });
}

onMounted(scrollToBottom);

watch(
    () => props.messages.length,
    () => scrollToBottom(),
);
</script>

<template>
    <Head :title="t('admin.support.show.headTitle', { name: conversation.user.name || t('admin.shared.client') })" />

    <AdminLayout>
        <template #title>{{ t('admin.support.show.title') }}</template>

        <AdminPage>
            <AdminBackLink :href="route('admin.support.index')" />

            <div class="admin-support-thread">
                <aside class="admin-support-thread__sidebar">
                    <p class="admin-support-thread__sidebar-title">{{ t('admin.support.show.sidebarTitle') }}</p>
                    <Link
                        v-for="item in conversations"
                        :key="item.id"
                        :href="route('admin.support.show', item.id)"
                        class="admin-support-thread__sidebar-item"
                        :class="{ 'admin-support-thread__sidebar-item--active': item.id === conversation.id }"
                    >
                        <span class="admin-support-thread__sidebar-name">
                            {{ item.user.name || t('admin.shared.client') }}
                            <a-badge v-if="item.unread_count > 0" :count="item.unread_count" />
                        </span>
                        <span class="admin-support-thread__sidebar-order">{{ orderLabel(item) }}</span>
                        <span class="admin-support-thread__sidebar-preview">{{ item.last_message_preview }}</span>
                    </Link>
                </aside>

                <section class="admin-support-thread__main">
                    <header class="admin-support-thread__header">
                        <div>
                            <h2 class="admin-support-thread__title">{{ conversation.user.name || t('admin.shared.client') }}</h2>
                            <p class="admin-support-thread__meta">
                                {{ orderLabel(conversation) }}
                                · {{ conversation.user.phone || conversation.user.email || `ID ${conversation.user.id}` }}
                            </p>
                        </div>
                        <div class="admin-support-thread__header-actions">
                            <Link
                                v-if="conversation.order"
                                :href="route('admin.orders.show', conversation.order.id)"
                                class="admin-support-thread__profile-link"
                            >
                                {{ t('admin.support.show.links.order') }}
                            </Link>
                            <Link :href="route('admin.users.show', conversation.user.id)" class="admin-support-thread__profile-link">
                                {{ t('admin.support.show.links.profile') }}
                            </Link>
                        </div>
                    </header>

                    <div ref="messagesEl" class="admin-support-thread__messages">
                        <article
                            v-for="message in messages"
                            :key="message.id"
                            class="admin-support-bubble"
                            :class="message.is_mine ? 'admin-support-bubble--mine' : 'admin-support-bubble--theirs'"
                        >
                            <p class="admin-support-bubble__body">{{ message.body }}</p>
                            <p class="admin-support-bubble__meta">
                                {{ message.is_mine ? t('admin.shared.you') : (message.sender_name || t('admin.shared.client')) }}
                                · {{ formatDate(message.created_at) }}
                            </p>
                        </article>
                    </div>

                    <form class="admin-support-thread__composer" @submit.prevent="submit">
                        <a-textarea
                            v-model:value="form.body"
                            :rows="3"
                            maxlength="2000"
                            :placeholder="t('admin.support.show.composer.placeholder')"
                            :disabled="form.processing"
                        />
                        <p v-if="form.errors.body" class="admin-support-thread__error">{{ form.errors.body }}</p>
                        <div class="admin-support-thread__actions">
                            <a-button type="primary" html-type="submit" :loading="form.processing" :disabled="!form.body.trim()">
                                {{ t('admin.shared.actions.send') }}
                            </a-button>
                        </div>
                    </form>
                </section>
            </div>
        </AdminPage>
    </AdminLayout>
</template>

<style scoped>
.admin-support-thread {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 16px;
    min-height: 70vh;
}

.admin-support-thread__sidebar {
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    padding: 12px;
    overflow: auto;
}

.admin-support-thread__sidebar-title {
    margin: 0 0 12px;
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #94a3b8;
}

.admin-support-thread__sidebar-item {
    display: block;
    padding: 10px;
    border-radius: 10px;
    text-decoration: none;
    color: inherit;
    margin-bottom: 6px;
}

.admin-support-thread__sidebar-item:hover,
.admin-support-thread__sidebar-item--active {
    background: #eff6ff;
}

.admin-support-thread__sidebar-name {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 600;
}

.admin-support-thread__sidebar-order {
    display: block;
    margin-top: 2px;
    color: #334155;
    font-size: 11px;
    font-weight: 600;
}

.admin-support-thread__sidebar-preview {
    display: block;
    margin-top: 4px;
    color: #64748b;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-support-thread__main {
    display: grid;
    grid-template-rows: auto 1fr auto;
    border: 1px solid #e2e8f0;
    border-radius: 14px;
    background: #fff;
    overflow: hidden;
}

.admin-support-thread__header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    padding: 16px;
    border-bottom: 1px solid #e2e8f0;
}

.admin-support-thread__title {
    margin: 0;
    font-size: 18px;
}

.admin-support-thread__meta {
    margin: 4px 0 0;
    color: #64748b;
    font-size: 13px;
}

.admin-support-thread__header-actions {
    display: flex;
    gap: 12px;
    align-items: center;
}

.admin-support-thread__profile-link {
    color: #1677ff;
    font-weight: 600;
    text-decoration: none;
}

.admin-support-thread__messages {
    padding: 16px;
    overflow-y: auto;
    background: #f8fafc;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.admin-support-bubble {
    max-width: 80%;
    padding: 10px 12px;
    border-radius: 14px;
}

.admin-support-bubble--mine {
    align-self: flex-end;
    background: #1677ff;
    color: #fff;
}

.admin-support-bubble--theirs {
    align-self: flex-start;
    background: #fff;
    border: 1px solid #e2e8f0;
}

.admin-support-bubble__body {
    margin: 0;
    white-space: pre-wrap;
}

.admin-support-bubble__meta {
    margin: 6px 0 0;
    font-size: 11px;
    opacity: 0.75;
}

.admin-support-thread__composer {
    padding: 16px;
    border-top: 1px solid #e2e8f0;
}

.admin-support-thread__error {
    margin: 8px 0 0;
    color: #dc2626;
    font-size: 13px;
}

.admin-support-thread__actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 12px;
}

@media (max-width: 960px) {
    .admin-support-thread {
        grid-template-columns: 1fr;
        min-height: calc(100dvh - 120px);
    }

    .admin-support-thread__sidebar {
        display: none;
    }

    .admin-support-thread__main {
        min-height: calc(100dvh - 180px);
    }

    .admin-support-thread__header {
        flex-direction: column;
        align-items: stretch;
    }

    .admin-support-thread__header-actions {
        flex-wrap: wrap;
    }

    .admin-support-thread__messages {
        min-height: 0;
    }

    .admin-support-bubble {
        max-width: 92%;
    }
}
</style>
