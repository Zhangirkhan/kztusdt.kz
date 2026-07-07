import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    build: {
        sourcemap: false,
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
            '@shared': resolve(__dirname, 'resources/js/shared'),
            '@entities': resolve(__dirname, 'resources/js/entities'),
            '@features': resolve(__dirname, 'resources/js/features'),
            '@widgets': resolve(__dirname, 'resources/js/widgets'),
        },
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
                theme_color: '#2563eb',
                background_color: '#eef1f6',
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
                maximumFileSizeToCacheInBytes: 1024 * 1024,
                navigateFallback: null,
            },
        }),
    ],
});
