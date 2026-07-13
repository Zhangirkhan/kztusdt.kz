import { i18n } from '@/i18n';

const WS_URLS = [
    'wss://127.0.0.1:13579',
    'wss://localhost:13579',
];

const REQUEST_TIMEOUT_MS = 120_000;

function parseResponse(raw) {
    if (typeof raw !== 'string') {
        return raw;
    }

    try {
        return JSON.parse(raw);
    } catch {
        return { body: raw };
    }
}

function extractSignature(response) {
    if (typeof response === 'string') {
        return response.replace(/\s+/g, '');
    }

    if (response?.body && typeof response.body === 'string') {
        return response.body.replace(/\s+/g, '');
    }

    if (response?.result && typeof response.result === 'string') {
        return response.result.replace(/\s+/g, '');
    }

    if (response?.responseObject && typeof response.responseObject === 'string') {
        return response.responseObject.replace(/\s+/g, '');
    }

    if (response?.code === '500' || response?.code === 500) {
        throw new Error(response.message || i18n.global.t('ncalayer.errors.generic'));
    }

    if (response?.status === false) {
        const details = response.details || response.message || i18n.global.t('ncalayer.errors.cancelled');

        throw new Error(typeof details === 'string' ? details : JSON.stringify(details));
    }

    throw new Error(i18n.global.t('ncalayer.errors.signatureMissing'));
}

function connectNcaLayer() {
    return new Promise((resolve, reject) => {
        let index = 0;
        let socket = null;
        let settled = false;

        const tryNext = () => {
            if (index >= WS_URLS.length) {
                if (! settled) {
                    settled = true;
                    reject(new Error(i18n.global.t('ncalayer.errors.notRunning')));
                }

                return;
            }

            const url = WS_URLS[index];
            index += 1;

            socket = new WebSocket(url);

            const timeout = setTimeout(() => {
                socket?.close();
                tryNext();
            }, 4_000);

            socket.onopen = () => {
                clearTimeout(timeout);

                if (! settled) {
                    settled = true;
                    resolve(socket);
                }
            };

            socket.onerror = () => {
                clearTimeout(timeout);
                tryNext();
            };
        };

        tryNext();
    });
}

function sendRequest(socket, payload) {
    return new Promise((resolve, reject) => {
        const timeout = setTimeout(() => {
            socket.close();
            reject(new Error(i18n.global.t('ncalayer.errors.timeout')));
        }, REQUEST_TIMEOUT_MS);

        socket.onmessage = (event) => {
            clearTimeout(timeout);
            socket.close();

            try {
                const parsed = parseResponse(event.data);
                resolve(parsed);
            } catch (error) {
                reject(error);
            }
        };

        socket.onerror = () => {
            clearTimeout(timeout);
            reject(new Error(i18n.global.t('ncalayer.errors.connection')));
        };

        socket.send(JSON.stringify(payload));
    });
}

/** Sign Base64 data with detached CMS signature via NCALayer desktop app. */
export async function signBase64Detached(base64Data) {
    const socket = await connectNcaLayer();

    const payload = {
        module: 'kz.gov.pki.knca.basics',
        method: 'sign',
        args: {
            allowedStorages: ['PKCS12', 'AKKaztoken', 'AKKZIDCardStore', 'AKEToken5110Store'],
            format: 'cms',
            data: base64Data,
            signingParams: {
                decode: true,
                encapsulate: false,
                digested: false,
                tsaProfile: null,
            },
            signerParams: {
                extKeyUsageOids: ['1.3.6.1.5.5.7.3.2', '1.3.6.1.5.5.7.3.4'],
            },
            locale: 'ru',
        },
    };

    const response = await sendRequest(socket, payload);

    return extractSignature(response);
}

export function useNcaLayer() {
    return {
        signBase64Detached,
        isAvailable: () => typeof window !== 'undefined' && 'WebSocket' in window,
    };
}
