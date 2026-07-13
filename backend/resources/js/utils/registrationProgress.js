const STORAGE_KEY = 'kztusdt_registration_progress_v1';

/**
 * @typedef {'otp' | 'app-lock' | 'kyc'} RegistrationStep
 * @typedef {{ loginCode: string, phone?: string, step: RegistrationStep, updatedAt: number }} RegistrationProgress
 */

/**
 * @returns {RegistrationProgress | null}
 */
export function readRegistrationProgress() {
    if (typeof window === 'undefined') {
        return null;
    }

    try {
        const raw = localStorage.getItem(STORAGE_KEY);

        if (!raw) {
            return null;
        }

        const data = JSON.parse(raw);

        if (!data?.loginCode || !data?.step) {
            return null;
        }

        return data;
    } catch {
        return null;
    }
}

/**
 * @param {{ loginCode: string, phone?: string, step: RegistrationStep }} progress
 */
export function writeRegistrationProgress(progress) {
    if (typeof window === 'undefined' || !progress?.loginCode || !progress?.step) {
        return;
    }

    localStorage.setItem(
        STORAGE_KEY,
        JSON.stringify({
            loginCode: progress.loginCode,
            phone: progress.phone ?? '',
            step: progress.step,
            updatedAt: Date.now(),
        }),
    );
}

export function clearRegistrationProgress() {
    if (typeof window === 'undefined') {
        return;
    }

    localStorage.removeItem(STORAGE_KEY);
}
