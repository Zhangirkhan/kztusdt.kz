import { resolve } from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

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
    ],
});
