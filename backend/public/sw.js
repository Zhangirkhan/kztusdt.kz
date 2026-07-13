/**
 * Push-only service worker for PWA notifications (v3).
 * Also purges legacy Workbox caches that caused stale admin assets.
 */
const SW_VERSION = 'v4';

self.addEventListener('install', () => {
    self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(
        caches.keys()
            .then((keys) => Promise.all(keys.map((key) => caches.delete(key))))
            .then(() => self.clients.claim()),
    );
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'kztusdt.kz', body: event.data ? event.data.text() : '' };
    }

    const title = payload.title || 'kztusdt.kz';
    const options = {
        body: payload.body || '',
        icon: '/icons/icon-192.png',
        badge: '/icons/icon-192.png',
        vibrate: [80, 40, 80],
        data: { url: payload.url || '/wallet' },
    };

    event.waitUntil(self.registration.showNotification(title, options));
});

function resolveNotificationUrl(rawUrl) {
    const fallback = new URL('/wallet', self.location.origin).href;

    if (!rawUrl || typeof rawUrl !== 'string') {
        return fallback;
    }

    try {
        return new URL(rawUrl, self.location.origin).href;
    } catch (e) {
        return fallback;
    }
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();

    const targetUrl = resolveNotificationUrl(event.notification.data && event.notification.data.url);

    event.waitUntil(
        self.clients
            .matchAll({ type: 'window', includeUncontrolled: true })
            .then((clientList) => {
                for (const client of clientList) {
                    if (!('focus' in client)) {
                        continue;
                    }

                    const navigate = 'navigate' in client
                        ? client.navigate(targetUrl).catch(() => {})
                        : Promise.resolve();

                    return navigate.then(() => client.focus());
                }

                if (self.clients.openWindow) {
                    return self.clients.openWindow(targetUrl);
                }

                return undefined;
            }),
    );
});
