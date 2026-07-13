import { computed, ref } from 'vue';
import { usePage } from '@inertiajs/vue3';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, body = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body),
    });

    if (! response.ok) {
        throw new Error('request_failed');
    }

    return response.json().catch(() => ({}));
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);
    const output = new Uint8Array(raw.length);

    for (let i = 0; i < raw.length; i++) {
        output[i] = raw.charCodeAt(i);
    }

    return output;
}

const isSupported = () =>
    typeof window !== 'undefined'
    && 'serviceWorker' in navigator
    && 'PushManager' in window
    && 'Notification' in window;

export function usePushNotifications() {
    const page = usePage();
    const supported = isSupported();
    const busy = ref(false);
    const error = ref(null);
    const permission = ref(supported ? Notification.permission : 'denied');

    const vapidKey = computed(() => page.props.push?.vapidPublicKey ?? '');
    const configured = computed(() => supported && vapidKey.value !== '');

    async function getRegistration() {
        await navigator.serviceWorker.register('/sw.js?v=9').catch(() => {});

        return navigator.serviceWorker.ready;
    }

    async function currentSubscription() {
        if (! supported) {
            return null;
        }

        const registration = await navigator.serviceWorker.ready;

        return registration.pushManager.getSubscription();
    }

    async function isSubscribed() {
        if (! supported || permission.value !== 'granted') {
            return false;
        }

        return (await currentSubscription()) !== null;
    }

    async function subscribe() {
        if (! configured.value) {
            return false;
        }

        busy.value = true;
        error.value = null;

        try {
            const result = await Notification.requestPermission();
            permission.value = result;

            if (result !== 'granted') {
                return false;
            }

            const registration = await getRegistration();
            let subscription = await registration.pushManager.getSubscription();

            if (! subscription) {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(vapidKey.value),
                });
            }

            await postJson('/api/push/subscribe', subscription.toJSON());

            return true;
        } catch (exception) {
            error.value = exception.message ?? 'push_failed';

            return false;
        } finally {
            busy.value = false;
        }
    }

    async function unsubscribe() {
        if (! supported) {
            return;
        }

        busy.value = true;

        try {
            const subscription = await currentSubscription();

            if (subscription) {
                await postJson('/api/push/unsubscribe', { endpoint: subscription.endpoint }).catch(() => {});
                await subscription.unsubscribe().catch(() => {});
            }
        } finally {
            busy.value = false;
        }
    }

    /**
     * Re-sync the server subscription when permission is already granted
     * (e.g. on a new device or after the server pruned a stale row).
     */
    async function syncIfGranted() {
        if (configured.value && permission.value === 'granted') {
            await subscribe();
        }
    }

    return {
        supported,
        configured,
        busy,
        error,
        permission,
        isSubscribed,
        subscribe,
        unsubscribe,
        syncIfGranted,
    };
}
