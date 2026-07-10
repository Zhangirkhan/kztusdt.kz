import { onUnmounted, ref } from 'vue';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function requestJson(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.body ? { 'Content-Type': 'application/json' } : {}),
            ...(options.method && options.method !== 'GET'
                ? { 'X-CSRF-TOKEN': csrfToken() }
                : {}),
            ...options.headers,
        },
        ...options,
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        throw new Error(data.message ?? 'request_failed');
    }

    return data;
}

export function useSupportChat(orderId) {
    const loading = ref(false);
    const sending = ref(false);
    const messages = ref([]);
    const unreadCount = ref(0);
    const draft = ref('');
    const error = ref(null);
    let pollTimer = null;
    let pollMode = null;

    const apiBase = `/api/support/chat/orders/${orderId}`;

    async function refreshUnread() {
        try {
            const data = await requestJson(`${apiBase}/unread`);
            unreadCount.value = data.unread_count ?? 0;
        } catch {
            // ignore background poll errors
        }
    }

    async function loadThread({ silent = false } = {}) {
        if (!silent) {
            loading.value = true;
        }

        error.value = null;

        try {
            const data = await requestJson(apiBase);
            messages.value = data.messages ?? [];
            unreadCount.value = 0;
        } catch {
            if (!silent) {
                error.value = 'Не удалось загрузить чат. Попробуйте ещё раз.';
            }
        } finally {
            if (!silent) {
                loading.value = false;
            }
        }
    }

    async function sendMessage() {
        const body = draft.value.trim();

        if (!body || sending.value) {
            return;
        }

        sending.value = true;
        error.value = null;

        try {
            const data = await requestJson(`${apiBase}/messages`, {
                method: 'POST',
                body: JSON.stringify({ body }),
            });

            if (data.message) {
                messages.value = [...messages.value, data.message];
            }

            if (data.auto_reply) {
                messages.value = [...messages.value, data.auto_reply];
            }

            draft.value = '';
        } catch (exception) {
            error.value = exception.message === 'request_failed'
                ? 'Не удалось отправить сообщение.'
                : exception.message;
        } finally {
            sending.value = false;
        }
    }

    function startPolling(mode) {
        stopPolling();
        pollMode = mode;

        pollTimer = window.setInterval(() => {
            if (pollMode === 'thread') {
                loadThread({ silent: true });
            } else {
                refreshUnread();
            }
        }, 4000);
    }

    function startThreadPolling() {
        startPolling('thread');
    }

    function startUnreadPolling() {
        startPolling('unread');
    }

    function stopPolling() {
        if (pollTimer !== null) {
            window.clearInterval(pollTimer);
            pollTimer = null;
        }

        pollMode = null;
    }

    onUnmounted(() => {
        stopPolling();
    });

    return {
        loading,
        sending,
        messages,
        unreadCount,
        draft,
        error,
        refreshUnread,
        loadThread,
        sendMessage,
        startThreadPolling,
        startUnreadPolling,
        stopPolling,
    };
}
