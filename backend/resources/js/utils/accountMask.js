export const KZ_IBAN_PREFIX = 'KZ';
export const KZ_IBAN_LENGTH = 20;

/**
 * Format Kazakhstan IBAN: KZ00 0000 0000 0000 0000
 */
export function formatKzIban(raw) {
    let value = String(raw).replace(/[^a-zA-Z0-9]/g, '').toUpperCase();

    if (value.length === 0) {
        return KZ_IBAN_PREFIX;
    }

    // Force prefix to "KZ" while typing.
    if (!value.startsWith('K')) {
        value = `${KZ_IBAN_PREFIX}${value}`;
    } else if (value.length === 1) {
        value = KZ_IBAN_PREFIX;
    } else if (!value.startsWith(KZ_IBAN_PREFIX)) {
        value = `${KZ_IBAN_PREFIX}${value.slice(1)}`;
    }

    value = value.slice(0, KZ_IBAN_LENGTH);

    // Group by 4 from the start: "KZ00 0000 0000 0000 0000"
    const groups = value.match(/.{1,4}/g) ?? [];
    return groups.join(' ').trim();
}

export function clearKzIbanMask() {
    return KZ_IBAN_PREFIX;
}

export function normalizeKzIban(value) {
    return String(value).replace(/\s/g, '').toUpperCase();
}

export function isKzIbanComplete(value) {
    return normalizeKzIban(value).length === KZ_IBAN_LENGTH;
}
