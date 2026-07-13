import { sha256d } from './sha256.js';
import { i18n } from '@/i18n';

/** EVM / BEP20: 0x + 40 hex chars */
export const EVM_ADDRESS_PREFIX = '0x';
export const EVM_ADDRESS_BODY_LENGTH = 40;
export const EVM_ADDRESS_LENGTH = EVM_ADDRESS_PREFIX.length + EVM_ADDRESS_BODY_LENGTH;

/** TRON / TRC20: Base58Check T… (34 chars); alphabet excludes 0, O, I, l */
export const TRON_ADDRESS_PREFIX = 'T';
export const TRON_ADDRESS_LENGTH = 34;
export const TRON_BASE58_ALPHABET = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
const TRON_BASE58_BODY = /[^1-9A-HJ-NP-Za-km-z]/g;

/**
 * @param {'evm'|'tron'|string} format
 */
export function clearWalletAddressMask(format) {
    return format === 'tron' ? TRON_ADDRESS_PREFIX : EVM_ADDRESS_PREFIX;
}

/**
 * @param {'evm'|'tron'|string} format
 */
export function walletAddressPlaceholder(format) {
    return format === 'tron' ? 'Txxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx' : '0x0000000000000000000000000000000000000000';
}

/**
 * @param {'evm'|'tron'|string} format
 */
export function walletAddressHint(format) {
    return format === 'tron'
        ? i18n.global.t('walletAddress.hint.tron')
        : i18n.global.t('walletAddress.hint.evm');
}

/**
 * @param {'evm'|'tron'|string} format
 */
export function walletAddressMaxLength(format) {
    return format === 'tron' ? TRON_ADDRESS_LENGTH : EVM_ADDRESS_LENGTH;
}

/**
 * Normalize pasted/typed withdraw address for the selected network format.
 * Keeps EIP-55 / Base58 casing; strips invalid characters and enforces prefix + max length.
 *
 * @param {string} raw
 * @param {'evm'|'tron'|string} format
 */
export function formatWalletAddress(raw, format) {
    if (format === 'tron') {
        return formatTronAddress(raw);
    }

    return formatEvmAddress(raw);
}

function formatEvmAddress(raw) {
    let value = String(raw ?? '').replace(/\s+/g, '');

    if (value.length === 0) {
        return '';
    }

    // Allow typing "0" then "x" without forcing the full prefix mid-paste of bare hex.
    if (/^0x/i.test(value)) {
        value = EVM_ADDRESS_PREFIX + value.slice(2).replace(/[^0-9a-fA-F]/g, '');
    } else if (value === '0' || value.toLowerCase() === '0x') {
        return value.length === 1 ? '0' : EVM_ADDRESS_PREFIX;
    } else {
        // Bare hex paste → prepend 0x
        value = EVM_ADDRESS_PREFIX + value.replace(/[^0-9a-fA-F]/g, '');
    }

    return value.slice(0, EVM_ADDRESS_LENGTH);
}

function formatTronAddress(raw) {
    let value = String(raw ?? '').replace(/\s+/g, '');

    if (value.length === 0) {
        return '';
    }

    if (value.startsWith(TRON_ADDRESS_PREFIX) || value.startsWith('t')) {
        value = TRON_ADDRESS_PREFIX + value.slice(1).replace(TRON_BASE58_BODY, '');
    } else {
        value = TRON_ADDRESS_PREFIX + value.replace(TRON_BASE58_BODY, '');
    }

    return value.slice(0, TRON_ADDRESS_LENGTH);
}

/**
 * Length/charset complete — not yet a cryptographic validity check.
 *
 * @param {string} value
 * @param {'evm'|'tron'|string} format
 */
export function isWalletAddressComplete(value, format) {
    const address = String(value ?? '');

    if (format === 'tron') {
        return /^T[1-9A-HJ-NP-Za-km-z]{33}$/.test(address);
    }

    return /^0x[0-9a-fA-F]{40}$/.test(address);
}

/**
 * Full client-side validity (TRON Base58Check / EVM length+hex).
 *
 * @param {string} value
 * @param {'evm'|'tron'|string} format
 */
export function isWalletAddressValid(value, format) {
    if (!isWalletAddressComplete(value, format)) {
        return false;
    }

    if (format === 'tron') {
        return isValidTronBase58Check(value);
    }

    return true;
}

/**
 * Human-readable validation message, or null when OK / still incomplete.
 *
 * @param {string} value
 * @param {'evm'|'tron'|string} format
 * @returns {string|null}
 */
export function walletAddressError(value, format) {
    const address = String(value ?? '');

    if (!address || address === clearWalletAddressMask(format)) {
        return null;
    }

    if (!isWalletAddressComplete(address, format)) {
        return format === 'tron'
            ? i18n.global.t('walletAddress.errors.tronLength')
            : i18n.global.t('walletAddress.errors.evmFormat');
    }

    if (format === 'tron' && !isValidTronBase58Check(address)) {
        return i18n.global.t('walletAddress.errors.tronChecksum');
    }

    return null;
}

function isValidTronBase58Check(address) {
    try {
        const payload = base58CheckDecode(address);

        return payload.length === 21 && payload[0] === 0x41;
    } catch {
        return false;
    }
}

/**
 * @param {string} encoded
 * @returns {Uint8Array}
 */
function base58CheckDecode(encoded) {
    const decoded = base58Decode(encoded);

    if (decoded.length < 5) {
        throw new Error('too short');
    }

    const payload = decoded.slice(0, -4);
    const checksum = decoded.slice(-4);
    const expected = sha256d(payload).slice(0, 4);

    for (let i = 0; i < 4; i++) {
        if (checksum[i] !== expected[i]) {
            throw new Error('bad checksum');
        }
    }

    return payload;
}

/**
 * @param {string} encoded
 * @returns {Uint8Array}
 */
function base58Decode(encoded) {
    let num = 0n;
    const base = 58n;

    for (let i = 0; i < encoded.length; i++) {
        const pos = TRON_BASE58_ALPHABET.indexOf(encoded[i]);

        if (pos < 0) {
            throw new Error('bad char');
        }

        num = num * base + BigInt(pos);
    }

    let zeros = 0;
    while (zeros < encoded.length && encoded[zeros] === '1') {
        zeros++;
    }

    const hex = num === 0n ? '' : num.toString(16);
    const paddedHex = hex.length % 2 === 0 ? hex : `0${hex}`;
    const body = paddedHex.length === 0
        ? new Uint8Array(0)
        : Uint8Array.from(paddedHex.match(/.{2}/g).map((b) => Number.parseInt(b, 16)));

    const out = new Uint8Array(zeros + body.length);
    out.set(body, zeros);

    return out;
}
