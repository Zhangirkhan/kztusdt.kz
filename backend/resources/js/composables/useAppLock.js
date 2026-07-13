import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { hashPin, verifyPin } from '@/utils/appLockCrypto';
import {
    APP_LOCK_IDLE_MS,
    APP_LOCK_PIN_LENGTH,
    clearSessionUnlocked,
    consumeBackgrounded,
    consumeReloading,
    isAppLockConfigured,
    isPageReload,
    isReloading,
    isSessionUnlocked,
    markBackgrounded,
    markReloading,
    markSessionUnlocked,
    readAppLockConfig,
    writeAppLockConfig,
} from '@/utils/appLockStorage';

const isLocked = ref(false);
const needsSetup = ref(false);
const lastActivityAt = ref(Date.now());
const activeUserId = ref(null);
let listenersAttached = false;

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

    const onPageHide = () => {
        markReloading();
    };

    window.addEventListener('beforeunload', onPageHide);
    window.addEventListener('pagehide', onPageHide);

    window.addEventListener('pageshow', (event) => {
        if (!event.persisted) {
            return;
        }

        const userId = activeUserId.value;

        if (!userId || needsSetup.value) {
            return;
        }

        if (isSessionUnlocked()) {
            isLocked.value = false;
            touchActivity();
        }
    });

    document.addEventListener('visibilitychange', () => {
        const userId = activeUserId.value;

        if (!userId || needsSetup.value || !isAppLockConfigured(userId)) {
            return;
        }

        if (document.visibilityState === 'hidden') {
            if (isReloading() || isPageReload()) {
                return;
            }

            markBackgrounded();

            return;
        }

        if (consumeReloading() || isPageReload()) {
            return;
        }

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

export function useAppLock() {
    const page = usePage();
    const userId = computed(() => page.props.auth?.user?.id ?? null);
    const configured = computed(() => (userId.value ? isAppLockConfigured(userId.value) : false));
    const biometricEnabled = computed(() => readAppLockConfig(userId.value)?.biometricEnabled === true);

    function syncState(id) {
        activeUserId.value = id;

        if (!id) {
            needsSetup.value = false;
            isLocked.value = false;

            return;
        }

        needsSetup.value = !isAppLockConfigured(id);

        if (needsSetup.value) {
            isLocked.value = true;

            return;
        }

        if (isSessionUnlocked()) {
            isLocked.value = false;
            touchActivity();
        } else {
            isLocked.value = true;
        }

        attachListeners();
    }

    watch(userId, (id) => syncState(id), { immediate: true });

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
    }

    return {
        pinLength: APP_LOCK_PIN_LENGTH,
        isLocked,
        needsSetup,
        configured,
        biometricEnabled,
        setupPin,
        changePin,
        verifyAppPin,
        unlock,
        setBiometricEnabled,
        markSessionUnlocked,
    };
}
