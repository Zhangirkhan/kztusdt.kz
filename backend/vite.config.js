import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    build: {
        sourcemap: false,
    },
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        VitePWA({
            registerType: 'autoUpdate',
            includeAssets: ['favicon.ico', 'icons/*.png'],
            manifest: {
                name: 'kztusdt.kz',
                short_name: 'kztusdt.kz',
                description: 'PWA крипто-обменник USDT / KZT',
                theme_color: '#0b0f14',
                background_color: '#0b0f14',
                display: 'standalone',
                orientation: 'portrait',
                start_url: '/',
                scope: '/',
                lang: 'ru',
                icons: [
                    {
                        src: '/icons/icon-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/icon-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/icon-512-maskable.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
            },
            injectRegister: false,
            workbox: {
                globIgnores: ['**/Admin/**', '**/Welcome*', '**/Login*', '**/Register*', '**/ForgotPassword*'],
                maximumFileSizeToCacheInBytes: 512 * 1024,
                navigateFallback: null,
            },
        }),
    ],
});
