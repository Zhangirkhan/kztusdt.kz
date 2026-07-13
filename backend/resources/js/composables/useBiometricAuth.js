import { ref } from 'vue';
import { navigateAfterAuth } from '@/utils/authNavigation';
import {
    BIOMETRIC_PHONE_STORAGE_KEY,
    isBiometricSupported,
    webAuthnRegister,
    webAuthnSign,
} from '@/utils/webAuthnClient';
import { extractKzDigits } from '@/utils/phoneMask';
import { i18n } from '@/i18n';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function extractErrorMessage(payload, fallback = i18n.global.t('biometric.errors.requestFailed')) {
    if (payload.errors && typeof payload.errors === 'object') {
        const firstField = Object.values(payload.errors).find(Array.isArray);

        if (firstField?.[0]) {
            return firstField[0];
        }
    }

    return payload.message ?? fallback;
}

function isBiometricCancelled(exception) {
    const name = exception?.name ?? '';
    const message = String(exception?.message ?? '');

    return name === 'NotAllowedError'
        || name === 'AbortError'
        || /not allowed|abort|cancel|denied/i.test(message);
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

    const payload = await response.json().catch(() => ({}));

    if (! response.ok) {
        throw new Error(extractErrorMessage(payload));
    }

    return payload;
}

export function normalizePhoneForApi(maskedPhone) {
    const digits = extractKzDigits(maskedPhone);

    if (digits.length !== 11) {
        return null;
    }

    return `+${digits}`;
}

export function useBiometricAuth() {
    const supported = isBiometricSupported();
    const busy = ref(false);
    const error = ref(null);

    function getSavedPhone() {
        return localStorage.getItem(BIOMETRIC_PHONE_STORAGE_KEY);
    }

    function savePhone(phone) {
        localStorage.setItem(BIOMETRIC_PHONE_STORAGE_KEY, phone);
    }

    async function checkAvailability(phone) {
        const normalized = normalizePhoneForApi(phone);

        if (! normalized) {
            return false;
        }

        const result = await postJson('/api/auth/biometric/check', { phone: normalized });

        return result.available === true;
    }

    async function loginWithBiometric(phone) {
        busy.value = true;
        error.value = null;

        try {
            const normalized = normalizePhoneForApi(phone);

            if (! normalized) {
                throw new Error(i18n.global.t('biometric.errors.invalidPhone'));
            }

            const { publicKey } = await postJson('/webauthn/auth/options', { phone: normalized });
            const credential = await webAuthnSign(publicKey);
            const result = await postJson('/webauthn/auth', {
                ...credential,
                phone: normalized,
                remember: 'on',
            });

            savePhone(normalized);

            navigateAfterAuth(result.callback ?? '/wallet');
        } catch (exception) {
            if (! isBiometricCancelled(exception)) {
                error.value = exception.message ?? i18n.global.t('biometric.errors.unavailable');
            }
            throw exception;
        } finally {
            busy.value = false;
        }
    }

    async function hasUnlockBiometric() {
        if (! supported) {
            return false;
        }

        try {
            const response = await fetch('/api/app-lock/biometric/options', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: '{}',
            });

            return response.ok;
        } catch {
            return false;
        }
    }

    async function registerBiometric(name = 'Face ID / fingerprint') {
        if (! supported) {
            throw new Error(i18n.global.t('biometric.errors.unsupported'));
        }

        busy.value = true;
        error.value = null;

        try {
            const { publicKey } = await postJson('/webauthn/keys/options');
            const credential = await webAuthnRegister(publicKey);

            await postJson('/webauthn/keys', {
                ...credential,
                name,
            });
        } catch (exception) {
            // Key may already exist on this device from an earlier setup.
            if (await hasUnlockBiometric()) {
                return true;
            }

            error.value = exception.message ?? i18n.global.t('biometric.errors.enableFailed');
            throw exception;
        } finally {
            busy.value = false;
        }
    }

    async function unlockWithBiometric() {
        busy.value = true;
        error.value = null;

        try {
            const { publicKey } = await postJson('/api/app-lock/biometric/options');
            const credential = await webAuthnSign(publicKey);
            await postJson('/api/app-lock/biometric/verify', credential);

            return true;
        } catch (exception) {
            error.value = exception.message ?? i18n.global.t('appLock.biometricFailed');
            throw exception;
        } finally {
            busy.value = false;
        }
    }

    return {
        supported,
        busy,
        error,
        getSavedPhone,
        savePhone,
        checkAvailability,
        hasUnlockBiometric,
        loginWithBiometric,
        registerBiometric,
        unlockWithBiometric,
    };
}
