<script setup>
import { useSupportChat } from '@/composables/useSupportChat';
import { localizedPath, unlocalizedPath } from '@/utils/localizedPath';
import { Link } from '@inertiajs/vue3';
import { computed, onMounted } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    orderId: {
        type: Number,
        required: true,
    },
    returnTo: {
        type: String,
        required: true,
    },
});
const { t } = useI18n();

const { unreadCount, refreshUnread, startUnreadPolling } = useSupportChat(props.orderId);

const chatHref = computed(() => {
    const back = encodeURIComponent(unlocalizedPath(props.returnTo));

    return localizedPath(`/support/chat?order=${props.orderId}&back=${back}`);
});

onMounted(() => {
    refreshUnread();
    startUnreadPolling();
});
</script>

<template>
    <Link
        :href="chatHref"
        class="support-chat-fab"
        :aria-label="t('support.chat.fabAria')"
    >
        <span class="material-symbols-outlined" aria-hidden="true">chat</span>
        <span v-if="unreadCount > 0" class="support-chat-fab__badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
    </Link>
</template>
