/** @type {readonly string[]} */
export const KZ_MOBILE_PREFIXES = [
    '700',
    '701',
    '702',
    '705',
    '706',
    '707',
    '708',
    '747',
    '771',
    '775',
    '776',
    '777',
    '778',
];

const MIN_PHONE = '+7';

/**
 * @param {string} national Digits after country code 7 (max 10).
 */
export function isPartialKzMobilePrefix(national) {
    if (national.length === 0) {
        return true;
    }

    if (national[0] !== '7') {
        return false;
    }

    if (national.length <= 3) {
        return KZ_MOBILE_PREFIXES.some((prefix) => prefix.startsWith(national));
    }

    return KZ_MOBILE_PREFIXES.includes(national.slice(0, 3));
}

/**
 * @param {string} national
 */
export function formatNational(national) {
    let digits = national.slice(0, 10);

    while (digits.length > 0 && !isPartialKzMobilePrefix(digits)) {
        digits = digits.slice(0, -1);
    }

    if (digits.length === 0) {
        return MIN_PHONE;
    }

    let formatted = `+7 (${digits.slice(0, 3)}`;

    if (digits.length <= 3) {
        return formatted;
    }

    formatted += `) ${digits.slice(3, 6)}`;

    if (digits.length <= 6) {
        return formatted;
    }

    formatted += `-${digits.slice(6, 8)}`;

    if (digits.length <= 8) {
        return formatted;
    }

    return `${formatted}-${digits.slice(8, 10)}`;
}

/**
 * Parse national digits from raw input.
 */
export function parseNationalDigits(raw) {
    let digits = String(raw).replace(/\D/g, '');

    if (digits.startsWith('8')) {
        digits = `7${digits.slice(1)}`;
    }

    if (digits.length === 0) {
        return '';
    }

    if (digits.length === 11 && digits.startsWith('7')) {
        return digits.slice(1);
    }

    if (digits.length === 10) {
        if (KZ_MOBILE_PREFIXES.includes(digits.slice(0, 3))) {
            return digits;
        }

        if (digits.startsWith('7') && KZ_MOBILE_PREFIXES.includes(digits.slice(1, 4))) {
            return digits.slice(1);
        }
    }

    if (digits.startsWith('7')) {
        if (digits.length === 1) {
            return '';
        }

        const asNational = digits.slice(1);
        if (asNational.length <= 10 && isPartialKzMobilePrefix(asNational)) {
            return asNational;
        }

        if (digits.length > 1 && digits.length <= 10 && isPartialKzMobilePrefix(digits)) {
            return digits;
        }

        return asNational.slice(0, 10);
    }

    return digits.slice(0, 10);
}

/**
 * Extract digits normalized to 7XXXXXXXXXX (KZ/RU country code).
 */
export function extractKzDigits(value) {
    const national = parseNationalDigits(value);

    if (national.length === 0) {
        return '7';
    }

    return `7${national}`.slice(0, 11);
}

/**
 * Update formatted phone on input/delete.
 */
export function updatePhoneMask(previousValue, inputValue) {
    const previousNational = parseNationalDigits(previousValue);
    const raw = String(inputValue).replace(/\D/g, '');
    const isDeleting = String(inputValue).length < String(previousValue).length
        || raw.length < previousNational.length + 1;

    if (raw.length === 0 || String(inputValue).trim() === '+') {
        return MIN_PHONE;
    }

    let national = parseNationalDigits(raw);

    if (isDeleting && national.length > previousNational.length) {
        national = previousNational.slice(0, Math.max(0, previousNational.length - 1));
    }

    while (national.length > 0 && !isPartialKzMobilePrefix(national)) {
        national = national.slice(0, -1);
    }

    return formatNational(national);
}

/** @deprecated Use updatePhoneMask */
export function formatKzPhone(value) {
    return updatePhoneMask(MIN_PHONE, value);
}

export function isKzPhoneComplete(value) {
    const digits = extractKzDigits(value);

    if (digits.length !== 11) {
        return false;
    }

    return KZ_MOBILE_PREFIXES.includes(digits.slice(1, 4));
}

/**
 * @param {string} value
 * @param {(key: string) => string} [translate]
 */
export function getKzPhoneError(value, translate) {
    const t = translate ?? ((key) => key);
    const national = parseNationalDigits(value);

    if (national.length === 0) {
        return null;
    }

    if (!isPartialKzMobilePrefix(national)) {
        return t('phone.errorInvalidPrefix');
    }

    if (national.length >= 3 && !KZ_MOBILE_PREFIXES.includes(national.slice(0, 3))) {
        return t('phone.errorInvalidOperator');
    }

    if (national.length > 0 && national.length < 10) {
        return null;
    }

    if (!isKzPhoneComplete(value)) {
        return t('phone.errorIncomplete');
    }

    return null;
}

export function clearPhoneMask() {
    return MIN_PHONE;
}

export { MIN_PHONE };
