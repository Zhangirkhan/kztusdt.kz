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

    if (digits.length === 10 && digits.startsWith('7')) {
        const operatorAt0 = digits.slice(0, 3);
        const operatorAt1 = digits.slice(1, 4);
        const prefixAt0 = KZ_MOBILE_PREFIXES.includes(operatorAt0);
        const prefixAt1 = KZ_MOBILE_PREFIXES.includes(operatorAt1);

        if (prefixAt1 && ! prefixAt0) {
            return digits.slice(1);
        }

        if (prefixAt0 && prefixAt1) {
            // 10 digits with both 7XX at [0:3] and [1:4] means "+7" + 9 national digits while typing.
            return digits.slice(1);
        }

        if (prefixAt0) {
            return digits;
        }
    }

    if (digits.startsWith('7')) {
        if (digits.length === 1) {
            return '';
        }

        return digits.slice(1, 11);
    }

    return digits.slice(0, 10);
}

/**
 * How many national digits (after +7) are before the caret.
 */
export function nationalDigitIndexBefore(formatted, caret) {
    const digits = formatted.slice(0, Math.max(0, caret)).replace(/\D/g, '');

    if (digits.length <= 1) {
        return 0;
    }

    return digits.length - 1;
}

/**
 * Caret position after the given count of national digits.
 */
export function caretForNationalDigitIndex(formatted, nationalDigitIndex) {
    if (nationalDigitIndex <= 0) {
        return Math.min(formatted.length, MIN_PHONE.length);
    }

    let nationalCount = 0;
    let skippedCountry = false;

    for (let i = 0; i < formatted.length; i++) {
        if (!/\d/.test(formatted[i])) {
            continue;
        }

        if (!skippedCountry) {
            skippedCountry = true;
            continue;
        }

        nationalCount++;

        if (nationalCount === nationalDigitIndex) {
            return i + 1;
        }
    }

    return formatted.length;
}

/**
 * Remove one national digit by 1-based index (as used before caret).
 */
export function removeNationalDigitAt(formatted, nationalIndex) {
    const national = parseNationalDigits(formatted);

    if (national.length === 0 || nationalIndex <= 0) {
        return MIN_PHONE;
    }

    const index = Math.min(nationalIndex, national.length) - 1;

    return formatNational(national.slice(0, index) + national.slice(index + 1));
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
 * Update formatted phone on input/paste.
 */
export function updatePhoneMask(previousValue, inputValue) {
    const prevNational = parseNationalDigits(previousValue);
    const raw = String(inputValue);
    const nextDigits = raw.replace(/\D/g, '');

    if (nextDigits.length === 0 || nextDigits === '7') {
        return MIN_PHONE;
    }

    const prevDigits = previousValue.replace(/\D/g, '');
    let national = parseNationalDigits(raw);

    if (
        nextDigits.length === 10
        && prevNational.length === 0
        && KZ_MOBILE_PREFIXES.includes(nextDigits.slice(0, 3))
    ) {
        national = nextDigits;
    } else if (nextDigits.length < prevDigits.length) {
        national = parseNationalDigits(nextDigits);
    } else if (raw.length < previousValue.length && national.length >= prevNational.length) {
        national = prevNational.slice(0, -1);
    } else if (
        nextDigits.length > prevDigits.length
        && nextDigits.startsWith('7')
        && prevDigits.startsWith('7')
        && prevNational.length > 0
    ) {
        const added = nextDigits.slice(prevDigits.length);
        const incremental = (prevNational + added).slice(0, 10);

        if (isPartialKzMobilePrefix(incremental)) {
            national = incremental;
        }
    }

    while (national.length > 0 && ! isPartialKzMobilePrefix(national)) {
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
