/**
 * @param {number|string|null|undefined} value
 * @param {number} [maxDecimals=8]
 */
export function formatDecimal(value, maxDecimals = 8) {
    const num = Number(value);

    if (!Number.isFinite(num)) {
        return '0';
    }

    return num.toFixed(maxDecimals).replace(/\.?0+$/, '');
}

/**
 * @param {number|string|null|undefined} value
 * @param {number} [maxDecimals=2]
 * @param {string} [locale='ru-RU']
 */
export function formatLocale(value, maxDecimals = 2, locale = 'ru-RU') {
    const trimmed = formatDecimal(value, maxDecimals);
    const [intPart, decPart] = trimmed.split('.');
    const formattedInt = Number(intPart).toLocaleString(locale);
    const decimalSep = new Intl.NumberFormat(locale).formatToParts(1.1).find((part) => part.type === 'decimal')?.value ?? ',';

    if (decPart === undefined) {
        return formattedInt;
    }

    return `${formattedInt}${decimalSep}${decPart}`;
}

/** @param {number|string|null|undefined} value */
export function formatKzt(value) {
    return formatLocale(value, 2);
}

/**
 * @param {number|string|null|undefined} value
 * @param {number} [maxDecimals=8]
 */
export function formatUsdt(value, maxDecimals = 8) {
    return formatDecimal(value, maxDecimals);
}

/** @param {number|string|null|undefined} value */
export function formatPercent(value) {
    return formatDecimal(value, 4);
}

/** @param {number|string|null|undefined} value */
export function formatRate(value) {
    return formatDecimal(value, 2);
}
