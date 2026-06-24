import { ref } from 'vue';
import { navigateAfterAuth } from '@/utils/authNavigation';
import {
    BIOMETRIC_PHONE_STORAGE_KEY,
    isBiometricSupported,
    webAuthnRegister,
    webAuthnSign,
} from '@/utils/webAuthnClient';
import { parseNationalDigits } from '@/utils/phoneMask';

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

function extractErrorMessage(payload, fallback = 'Не удалось выполнить запрос.') {
    if (payload.errors && typeof payload.errors === 'object') {
        const firstField = Object.values(payload.errors).find(Array.isArray);

        if (firstField?.[0]) {
            return firstField[0];
        }
    }

    return payload.message ?? fallback;
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
    const digits = parseNationalDigits(maskedPhone);

    if (digits.length !== 11 || ! digits.startsWith('7')) {
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
        const normalized = normalizePhoneForApi(phone) ?? phone;

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
            const normalized = normalizePhoneForApi(phone) ?? phone;

            if (! normalized) {
                throw new Error('Введите корректный номер телефона.');
            }

            const { publicKey } = await postJson('/webauthn/auth/options', { phone: normalized });
            const credential = await webAuthnSign(publicKey);
            const result = await postJson('/webauthn/auth', {
                ...credential,
                phone: normalized,
                remember: true,
            });

            savePhone(normalized);

            navigateAfterAuth(result.callback ?? '/home');
        } catch (exception) {
            error.value = exception.message ?? 'Биометрия недоступна.';
            throw exception;
        } finally {
            busy.value = false;
        }
    }

    async function registerBiometric(name = 'Face ID / отпечаток') {
        if (! supported) {
            throw new Error('Биометрия не поддерживается на этом устройстве.');
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
            error.value = exception.message ?? 'Не удалось включить биометрию.';
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
        loginWithBiometric,
        registerBiometric,
    };
}
