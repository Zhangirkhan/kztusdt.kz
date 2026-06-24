import { createI18n } from 'vue-i18n';
import en from './locales/en';
import kk from './locales/kk';
import ru from './locales/ru';

export const supportedLocales = ['ru', 'kk', 'en'];

export const i18n = createI18n({
    legacy: false,
    locale: 'ru',
    fallbackLocale: 'ru',
    messages: {
        ru,
        kk,
        en,
    },
});

export function applyLocale(locale) {
    if (!supportedLocales.includes(locale)) {
        return;
    }

    i18n.global.locale.value = locale;
    document.documentElement.lang = locale;
}
