/**
 * Sanitize a decimal amount string while typing.
 * Keeps at most one decimal point, strips invalid chars,
 * and clamps to maxDecimals fractional digits.
 *
 * @param {string|number} raw
 * @param {{ maxDecimals?: number }} [options]
 * @returns {string}
 */
export function sanitizeDecimalAmount(raw, options = {}) {
    const maxDecimals = options.maxDecimals ?? 2;
    let value = String(raw ?? '').replace(/,/g, '.').replace(/[^\d.]/g, '');

    if (value === '') {
        return '';
    }

    const firstDot = value.indexOf('.');

    if (firstDot !== -1) {
        const whole = value.slice(0, firstDot);
        const fraction = value.slice(firstDot + 1).replace(/\./g, '').slice(0, Math.max(0, maxDecimals));
        value = `${normalizeWhole(whole)}.${fraction}`;
        // Preserve trailing dot while typing "12."
        if (fraction.length === 0 && String(raw).replace(/,/g, '.').includes('.')) {
            return `${normalizeWhole(whole)}.`;
        }

        return value;
    }

    return normalizeWhole(value);
}

function normalizeWhole(whole) {
    if (whole === '') {
        return '0';
    }

    if (whole.startsWith('0') && whole.length > 1) {
        return whole.replace(/^0+/, '') || '0';
    }

    return whole;
}

/**
 * Clamp a numeric amount string to [0, max], formatted for input.
 *
 * @param {string} raw
 * @param {number} max
 * @param {{ maxDecimals?: number }} [options]
 * @returns {string}
 */
export function clampDecimalAmount(raw, max, options = {}) {
    const maxDecimals = options.maxDecimals ?? 2;
    const sanitized = sanitizeDecimalAmount(raw, { maxDecimals });

    if (sanitized === '' || sanitized === '.') {
        return sanitized === '.' ? '0.' : '';
    }

    // Still typing fractional part — don't clamp away trailing dot
    if (sanitized.endsWith('.')) {
        const value = Number.parseFloat(sanitized);

        if (!Number.isNaN(value) && Number.isFinite(max) && value > max) {
            return formatAmountForInput(Math.max(0, max), maxDecimals);
        }

        return sanitized;
    }

    const value = Number.parseFloat(sanitized);

    if (Number.isNaN(value)) {
        return '';
    }

    if (value < 0) {
        return '0';
    }

    const limit = Number.isFinite(max) ? Math.max(0, max) : Number.POSITIVE_INFINITY;

    if (value > limit) {
        return formatAmountForInput(limit, maxDecimals);
    }

    return sanitized;
}

/**
 * @param {number} amount
 * @param {number} maxDecimals
 */
export function formatAmountForInput(amount, maxDecimals = 2) {
    if (!Number.isFinite(amount)) {
        return '';
    }

    const factor = 10 ** maxDecimals;
    const rounded = Math.round(amount * factor) / factor;

    if (Number.isInteger(rounded)) {
        return String(rounded);
    }

    return String(Number(rounded.toFixed(maxDecimals)));
}

/**
 * Max withdrawable net amount given available balance, fee %, and flat network fee.
 * Solves: amount + amount*fee%/100 + networkFee <= available
 *
 * @param {number|string} available
 * @param {number|string} feePercent
 * @param {number|string} networkFee
 * @returns {number}
 */
export function maxWithdrawableAmount(available, feePercent, networkFee) {
    const avail = Number.parseFloat(String(available)) || 0;
    const fee = Number.parseFloat(String(feePercent)) || 0;
    const net = Number.parseFloat(String(networkFee)) || 0;
    const room = avail - net;

    if (room <= 0) {
        return 0;
    }

    const divisor = 1 + fee / 100;

    if (divisor <= 0) {
        return 0;
    }

    const max = room / divisor;

    return max > 0 ? Math.floor(max * 100) / 100 : 0;
}
