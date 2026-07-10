const SUPPORTED_LOCALES = ['ru', 'kk', 'en'];

function splitUrl(url) {
    const match = String(url).match(/^([^?#]*)([?#].*)?$/);

    return {
        path: match?.[1] || '/',
        suffix: match?.[2] || '',
    };
}

export function currentLocale() {
    const firstSegment = window.location.pathname.split('/').filter(Boolean)[0];

    return SUPPORTED_LOCALES.includes(firstSegment) ? firstSegment : 'ru';
}

export function unlocalizedPath(url = window.location.pathname) {
    const { path, suffix } = splitUrl(url);
    const segments = path.split('/').filter(Boolean);

    if (SUPPORTED_LOCALES.includes(segments[0])) {
        segments.shift();
    }

    return `/${segments.join('/')}${suffix}`.replace(/\/$/, '') || '/';
}

export function localizedPath(url = '/') {
    if (/^(https?:)?\/\//.test(url) || url.startsWith('mailto:') || url.startsWith('tel:')) {
        return url;
    }

    const { path, suffix } = splitUrl(url.startsWith('/') ? url : `/${url}`);
    const cleanPath = unlocalizedPath(path);

    if (
        cleanPath.startsWith('/admin')
        || cleanPath.startsWith('/api')
        || cleanPath.startsWith('/webauthn')
        || cleanPath.startsWith('/auth/aitu')
    ) {
        return `${cleanPath}${suffix}`;
    }

    if (cleanPath === '/') {
        return `/${currentLocale()}${suffix}`;
    }

    return `/${currentLocale()}${cleanPath}${suffix}`;
}

export function localizedPathFor(locale, url = window.location.pathname + window.location.search + window.location.hash) {
    if (!SUPPORTED_LOCALES.includes(locale)) {
        locale = 'ru';
    }

    if (/^(https?:)?\/\//.test(url) || url.startsWith('mailto:') || url.startsWith('tel:')) {
        return url;
    }

    const { path, suffix } = splitUrl(url.startsWith('/') ? url : `/${url}`);
    const cleanPath = unlocalizedPath(path);

    if (
        cleanPath.startsWith('/admin')
        || cleanPath.startsWith('/api')
        || cleanPath.startsWith('/webauthn')
        || cleanPath.startsWith('/auth/aitu')
    ) {
        return `${cleanPath}${suffix}`;
    }

    if (cleanPath === '/') {
        return `/${locale}${suffix}`;
    }

    return `/${locale}${cleanPath}${suffix}`;
}

export function localizedRoute(name, params, absolute, config) {
    return route(name, params, absolute, config);
}

export function isActivePath(currentUrl, targetPath) {
    const current = unlocalizedPath(currentUrl).split('?')[0];
    const target = unlocalizedPath(targetPath).split('?')[0];

    return current === target || current.startsWith(`${target}/`);
}
