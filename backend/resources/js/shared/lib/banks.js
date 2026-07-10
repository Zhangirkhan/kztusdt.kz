export const BANK_META = {
    kaspi: {
        short: 'K',
        tone: 'kaspi',
        label: 'Kaspi Bank',
        logo: '/images/banks/kaspi.svg',
    },
    bcc: {
        short: 'B',
        tone: 'bcc',
        label: 'Банк ЦентрКредит',
        logo: '/images/banks/bcc.svg',
    },
    altyn: {
        short: 'A',
        tone: 'altyn',
        label: 'Altyn Bank',
        logo: '/images/banks/altyn.svg',
    },
    halyk: {
        short: 'H',
        tone: 'halyk',
        label: 'Halyk Bank',
        logo: '/images/banks/halyk.svg',
    },
    freedom: {
        short: 'F',
        tone: 'freedom',
        label: 'Freedom Bank',
        logo: '/images/banks/freedom.svg',
    },
    jusan: {
        short: 'J',
        tone: 'jusan',
        label: 'Jusan Bank',
        logo: '/images/banks/jusan.svg',
    },
    forte: {
        short: 'F',
        tone: 'forte',
        label: 'ForteBank',
        logo: '/images/banks/forte.svg',
    },
};

export function bankTone(code) {
    return BANK_META[code]?.tone ?? 'default';
}

export function bankShort(code) {
    return BANK_META[code]?.short ?? String(code || '?').slice(0, 1).toUpperCase();
}

export function bankLogoSrc(code) {
    return BANK_META[code]?.logo ?? null;
}
