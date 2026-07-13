import { i18n } from '@/i18n';

const LOCALE_MAP = {
    ru: 'ru-RU',
    kk: 'kk-KZ',
    en: 'en-US',
};

export function resolveDateLocale(locale = 'ru') {
    return LOCALE_MAP[locale] ?? LOCALE_MAP.ru;
}

export function formatDate(value, locale = 'ru') {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleDateString(resolveDateLocale(locale));
}

export function formatDateTime(value, locale = 'ru-RU') {
    if (!value) {
        return i18n.global.t('common.empty');
    }

    return new Date(value).toLocaleString(locale);
}

export function formatTime(value, locale = 'ru-RU') {
    if (!value) {
        return '';
    }

    return new Date(value).toLocaleTimeString(locale, { hour: '2-digit', minute: '2-digit' });
}

export function formatHistoryGroupLabel(isoDate) {
    if (!isoDate) {
        return i18n.global.t('history.group.noDate');
    }

    return new Date(isoDate).toLocaleDateString(resolveDateLocale(i18n.global.locale.value), {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
}
