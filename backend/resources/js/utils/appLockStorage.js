export const APP_LOCK_PIN_LENGTH = 4;
export const APP_LOCK_IDLE_MS = 5 * 60 * 1000;
export const APP_LOCK_SESSION_UNLOCKED_KEY = 'kztusdt_app_lock_session_unlocked';
export const APP_LOCK_RELOADING_KEY = 'kztusdt_app_lock_reloading';
export const APP_LOCK_BACKGROUNDED_AT_KEY = 'kztusdt_app_lock_backgrounded_at';

function storageKey(userId) {
    return `kztusdt_app_lock_v1_${userId}`;
}

export function readAppLockConfig(userId) {
    if (!userId || typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(storageKey(userId));

        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

export function writeAppLockConfig(userId, config) {
    if (!userId || typeof window === 'undefined') {
        return;
    }

    localStorage.setItem(storageKey(userId), JSON.stringify(config));
}

export function clearAppLockConfig(userId) {
    if (!userId || typeof window === 'undefined') {
        return;
    }

    localStorage.removeItem(storageKey(userId));
}

export function isAppLockConfigured(userId) {
    const config = readAppLockConfig(userId);

    return Boolean(config?.pinHash && config?.pinSalt);
}

export function isSessionUnlocked() {
    if (typeof window === 'undefined') {
        return false;
    }

    return sessionStorage.getItem(APP_LOCK_SESSION_UNLOCKED_KEY) === '1';
}

export function markSessionUnlocked() {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.setItem(APP_LOCK_SESSION_UNLOCKED_KEY, '1');
}

export function clearSessionUnlocked() {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.removeItem(APP_LOCK_SESSION_UNLOCKED_KEY);
}

export function markReloading() {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.setItem(APP_LOCK_RELOADING_KEY, '1');
}

export function isReloading() {
    if (typeof window === 'undefined') {
        return false;
    }

    return sessionStorage.getItem(APP_LOCK_RELOADING_KEY) === '1';
}

export function consumeReloading() {
    if (typeof window === 'undefined') {
        return false;
    }

    const reloading = isReloading();

    if (reloading) {
        sessionStorage.removeItem(APP_LOCK_RELOADING_KEY);
    }

    return reloading;
}

export function markBackgrounded() {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.setItem(APP_LOCK_BACKGROUNDED_AT_KEY, String(Date.now()));
}

export function consumeBackgrounded() {
    if (typeof window === 'undefined') {
        return false;
    }

    const backgrounded = sessionStorage.getItem(APP_LOCK_BACKGROUNDED_AT_KEY);

    if (!backgrounded) {
        return false;
    }

    sessionStorage.removeItem(APP_LOCK_BACKGROUNDED_AT_KEY);

    return true;
}

export function isPageReload() {
    if (typeof window === 'undefined' || typeof performance === 'undefined') {
        return false;
    }

    const entry = performance.getEntriesByType('navigation')[0];

    return entry?.type === 'reload';
}
