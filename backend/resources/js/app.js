import '../css/app.css';
import './bootstrap';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { applyLocale, i18n } from './i18n';

const appName = import.meta.env.VITE_APP_NAME || 'kztusdt.kz';

createInertiaApp({
    title: (title) => (title.includes(appName) ? title : `${title} - ${appName}`),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        applyLocale(props.initialPage.props.locale?.current ?? 'ru');

        router.on('navigate', (event) => {
            applyLocale(event.detail.page.props.locale?.current ?? 'ru');
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
