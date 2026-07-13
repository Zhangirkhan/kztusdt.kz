import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { hashPin, verifyPin } from '@/utils/appLockCrypto';
import {
    APP_LOCK_IDLE_MS,
    APP_LOCK_PIN_LENGTH,
    APP_LOCK_BACKGROUNDED_AT_KEY,
    clearSessionUnlocked,
    consumeBackgrounded,
    consumeReloading,
    isAppLockConfigured,
    isPageReload,
    isSessionUnlocked,
    markBackgrounded,
    markReloading,
    markSessionUnlocked,
    readAppLockConfig,
    writeAppLockConfig,
} from '@/utils/appLockStorage';

const isLocked = ref(false);
const needsSetup = ref(false);
const configuredState = ref(false);
const biometricEnabledState = ref(false);
const lastActivityAt = ref(Date.now());
const activeUserId = ref(null);
let listenersAttached = false;
let userIdWatchAttached = false;

function refreshConfigFlags(id) {
    const config = readAppLockConfig(id);

    configuredState.value = Boolean(config?.pinHash && config?.pinSalt);
    biometricEnabledState.value = config?.biometricEnabled === true;
}

function clearStaleBackgroundMark() {
    if (typeof window === 'undefined') {
        return;
    }

    sessionStorage.removeItem(APP_LOCK_BACKGROUNDED_AT_KEY);
}

function touchActivity() {
    lastActivityAt.value = Date.now();
}

function lockApp() {
    clearSessionUnlocked();
    isLocked.value = true;
}

function unlockApp() {
    markSessionUnlocked();
    isLocked.value = false;
    touchActivity();
}

function attachListeners() {
    if (listenersAttached || typeof window === 'undefined') {
        return;
    }

    listenersAttached = true;

    const onActivity = () => {
        if (!isLocked.value) {
            touchActivity();
        }
    };

    ['click', 'keydown', 'touchstart', 'scroll'].forEach((eventName) => {
        window.addEventListener(eventName, onActivity, { passive: true });
    });

    window.addEventListener('beforeunload', () => {
        markReloading();
    });

    document.addEventListener('visibilitychange', () => {
        const userId = activeUserId.value;

        if (!userId || needsSetup.value || !isAppLockConfigured(userId)) {
            return;
        }

        if (document.visibilityState === 'hidden') {
            markBackgrounded();

            return;
        }

        if (consumeReloading()) {
            clearStaleBackgroundMark();

            return;
        }

        // Ignore brief switches (control center, incoming call UI, etc.).
        if (consumeBackgrounded()) {
            lockApp();
        }
    });

    window.setInterval(() => {
        const userId = activeUserId.value;

        if (!userId || isLocked.value || needsSetup.value || document.visibilityState !== 'visible') {
            return;
        }

        if (!isAppLockConfigured(userId)) {
            return;
        }

        if (Date.now() - lastActivityAt.value >= APP_LOCK_IDLE_MS) {
            lockApp();
        }
    }, 15_000);
}

function syncState(id) {
    activeUserId.value = id;

    if (!id) {
        needsSetup.value = false;
        isLocked.value = false;
        configuredState.value = false;
        biometricEnabledState.value = false;

        return;
    }

    if (consumeReloading() || isPageReload()) {
        clearStaleBackgroundMark();
    }

    needsSetup.value = !isAppLockConfigured(id);
    refreshConfigFlags(id);
    attachListeners();

    if (needsSetup.value) {
        isLocked.value = true;

        return;
    }

    // Stay unlocked for the whole tab/PWA session until background (≥30s) or idle (5 min).
    if (isSessionUnlocked()) {
        isLocked.value = false;
        touchActivity();
    } else {
        isLocked.value = true;
    }
}

export function useAppLock() {
    const page = usePage();
    const userId = computed(() => page.props.auth?.user?.id ?? null);

    if (!userIdWatchAttached) {
        userIdWatchAttached = true;
        watch(userId, (id) => syncState(id), { immediate: true });
    } else {
        refreshConfigFlags(userId.value);
    }

    async function setupPin(pin, enableBiometric = false) {
        if (!userId.value || pin.length !== APP_LOCK_PIN_LENGTH) {
            return false;
        }

        const { hash, salt } = await hashPin(pin);

        writeAppLockConfig(userId.value, {
            pinHash: hash,
            pinSalt: salt,
            biometricEnabled: enableBiometric,
            configuredAt: new Date().toISOString(),
        });

        needsSetup.value = false;
        refreshConfigFlags(userId.value);
        attachListeners();
        unlockApp();

        return true;
    }

    async function changePin(currentPin, nextPin) {
        if (!userId.value || nextPin.length !== APP_LOCK_PIN_LENGTH) {
            return false;
        }

        const config = readAppLockConfig(userId.value);

        if (!config || !await verifyPin(currentPin, config.pinSalt, config.pinHash)) {
            return false;
        }

        const { hash, salt } = await hashPin(nextPin);

        writeAppLockConfig(userId.value, {
            ...config,
            pinHash: hash,
            pinSalt: salt,
        });
        refreshConfigFlags(userId.value);

        return true;
    }

    async function verifyAppPin(pin) {
        if (!userId.value) {
            return false;
        }

        const config = readAppLockConfig(userId.value);

        if (!config) {
            return false;
        }

        const valid = await verifyPin(pin, config.pinSalt, config.pinHash);

        if (valid) {
            unlockApp();
        }

        return valid;
    }

    function unlock() {
        unlockApp();
    }

    function setBiometricEnabled(enabled) {
        if (!userId.value) {
            return;
        }

        const config = readAppLockConfig(userId.value);

        if (!config) {
            return;
        }

        writeAppLockConfig(userId.value, {
            ...config,
            biometricEnabled: enabled,
        });
        refreshConfigFlags(userId.value);
    }

    return {
        pinLength: APP_LOCK_PIN_LENGTH,
        isLocked,
        needsSetup,
        configured: configuredState,
        biometricEnabled: biometricEnabledState,
        setupPin,
        changePin,
        verifyAppPin,
        unlock,
        setBiometricEnabled,
        markSessionUnlocked,
    };
}
