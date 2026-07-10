import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { applyLocale, i18n } from './i18n';
import { applyThemePreference, getStoredTheme } from './composables/useTheme';
import { showServerErrorOverlay } from './serverErrorOverlay';

const appName = import.meta.env.VITE_APP_NAME || 'kztusdt.kz';

function applyZiggy(ziggy) {
    if (ziggy) {
        globalThis.Ziggy = ziggy;
    }
}

createInertiaApp({
    title: (title) => (title.includes(appName) ? title : `${title} - ${appName}`),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        applyLocale(props.initialPage.props.locale?.current ?? 'ru');
        applyThemePreference(getStoredTheme());
        applyZiggy(props.initialPage.props.ziggy);

        router.on('navigate', (event) => {
            applyLocale(event.detail.page.props.locale?.current ?? 'ru');
            applyZiggy(event.detail.page.props.ziggy);
        });

        router.on('invalid', (event) => {
            const html = event.detail.response?.data;
            if (typeof html !== 'string' || !html.includes('class="panel"')) {
                return;
            }

            event.preventDefault();
            showServerErrorOverlay(html);
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(i18n)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
