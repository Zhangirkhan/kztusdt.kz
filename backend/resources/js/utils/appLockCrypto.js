const PBKDF2_ITERATIONS = 120_000;

function bufferToBase64(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)));
}

function base64ToBuffer(value) {
    const text = atob(value);

    return Uint8Array.from(text, (char) => char.charCodeAt(0));
}

async function derivePinHash(pin, saltBase64) {
    const encoder = new TextEncoder();
    const keyMaterial = await crypto.subtle.importKey(
        'raw',
        encoder.encode(pin),
        'PBKDF2',
        false,
        ['deriveBits'],
    );

    const bits = await crypto.subtle.deriveBits(
        {
            name: 'PBKDF2',
            salt: base64ToBuffer(saltBase64),
            iterations: PBKDF2_ITERATIONS,
            hash: 'SHA-256',
        },
        keyMaterial,
        256,
    );

    return bufferToBase64(bits);
}

export async function hashPin(pin) {
    const salt = bufferToBase64(crypto.getRandomValues(new Uint8Array(16)));

    return {
        salt,
        hash: await derivePinHash(pin, salt),
    };
}

export async function verifyPin(pin, salt, hash) {
    const candidate = await derivePinHash(pin, salt);

    return candidate === hash;
}
