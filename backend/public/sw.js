/**
 * Push-only service worker for PWA notifications.
 * Also purges legacy Workbox caches that caused stale admin assets.
 *
 * Fetch listener is intentionally a no-op (no respondWith): Chrome still treats
 * it as installable, but intercepting every request with fetch() caused white
 * screens in the installed client PWA (especially WebKit standalone).
 */
const SW_VERSION = 'v9';

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

self.addEventListener('fetch', () => {
    // no-op — do not intercept navigations/assets
});

self.addEventListener('push', (event) => {
    let payload = {};

    try {
        payload = event.data ? event.data.json() : {};
    } catch (e) {
        payload = { title: 'KZTUSDT', body: event.data ? event.data.text() : '' };
    }

    // Light mark (transparent bg) stays visible in dark notification trays;
    // charcoal PWA icons (/icons/icon-192.png) blend into the shade.
    const iconUrl = new URL('/logo.png', self.location.origin).href;
    const badgeUrl = new URL('/icons/icon-32.png', self.location.origin).href;

    const title = payload.title || 'KZTUSDT';
    const options = {
        body: payload.body || '',
        icon: iconUrl,
        badge: badgeUrl,
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
