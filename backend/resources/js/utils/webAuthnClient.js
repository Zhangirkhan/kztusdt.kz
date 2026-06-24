'use strict';

function bufferEncode(value) {
    return window.btoa(String.fromCharCode.apply(null, new Uint8Array(value)));
}

function bufferDecode(value) {
    const text = window.atob(value);

    return Uint8Array.from(text, (char) => char.charCodeAt(0));
}

function base64Decode(input) {
    let normalized = input.replace(/-/g, '+').replace(/_/g, '/');
    const pad = normalized.length % 4;

    if (pad) {
        if (pad === 1) {
            throw new Error('Invalid base64url string');
        }

        normalized += '='.repeat(4 - pad);
    }

    return normalized;
}

function credentialDecode(credentials) {
    return credentials.map((data) => ({
        id: bufferDecode(base64Decode(data.id)),
        type: data.type,
        transports: data.transports,
    }));
}

export function isBiometricSupported() {
    return typeof window !== 'undefined'
        && window.PublicKeyCredential !== undefined
        && typeof window.PublicKeyCredential === 'function';
}

export function webAuthnRegister(publicKey) {
    const options = { ...publicKey };
    options.user.id = bufferDecode(publicKey.user.id);
    options.challenge = bufferDecode(base64Decode(publicKey.challenge));

    if (options.excludeCredentials) {
        options.excludeCredentials = credentialDecode(options.excludeCredentials);
    }

    return navigator.credentials.create({ publicKey: options }).then((credential) => ({
        id: credential.id,
        type: credential.type,
        rawId: bufferEncode(credential.rawId),
        response: {
            clientDataJSON: bufferEncode(credential.response.clientDataJSON).replace(/=/g, ''),
            attestationObject: bufferEncode(credential.response.attestationObject),
        },
    }));
}

export function webAuthnSign(publicKey) {
    const options = { ...publicKey };
    options.challenge = bufferDecode(base64Decode(publicKey.challenge));

    if (options.allowCredentials) {
        options.allowCredentials = credentialDecode(options.allowCredentials);
    }

    return navigator.credentials.get({ publicKey: options }).then((credential) => ({
        id: credential.id,
        type: credential.type,
        rawId: bufferEncode(credential.rawId),
        response: {
            authenticatorData: bufferEncode(credential.response.authenticatorData).replace(/=/g, ''),
            clientDataJSON: bufferEncode(credential.response.clientDataJSON).replace(/=/g, ''),
            signature: bufferEncode(credential.response.signature),
            userHandle: credential.response.userHandle
                ? bufferEncode(credential.response.userHandle)
                : null,
        },
    }));
}

export const BIOMETRIC_PHONE_STORAGE_KEY = 'kztusdt_biometric_phone';
