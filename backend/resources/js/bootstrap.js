/**
 * Application bootstrap (shared client setup).
 */
if (typeof window !== 'undefined') {
    window.__KZTUSDT_APP_BOOTED__ = true;
    document.getElementById('app-boot-splash')?.remove();
}
