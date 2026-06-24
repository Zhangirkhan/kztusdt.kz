import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                accent: '#13ec6d',
                'on-accent': '#002114',
                background: '#0b0f14',
                surface: '#111820',
                'surface-container': '#1a222d',
                'surface-container-low': '#151c26',
                'surface-container-lowest': '#0e141c',
                'surface-container-high': '#212b38',
                'on-surface': '#e8eef5',
                'on-surface-variant': '#9aa8b8',
                'text-muted': '#64748B',
                'text-dim': '#94A3B8',
                'outline-variant': '#2a3544',
                'input-border': '#2a3544',
                primary: '#13ec6d',
                'primary-container': '#0a3d24',
                error: '#ff6b6b',
                success: '#13ec6d',
            },
            borderRadius: {
                xl: '12px',
                '2xl': '16px',
            },
            spacing: {
                'container-max': '448px',
                'margin-page': '1.5rem',
                'input-padding': '1rem',
                'stack-section': '2rem',
                'stack-element': '1.25rem',
            },
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'headline-xl': ['30px', { lineHeight: '36px', fontWeight: '700' }],
                'headline-md': ['18px', { lineHeight: '24px', fontWeight: '700' }],
                'body-lg': ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'body-sm': ['14px', { lineHeight: '20px', fontWeight: '400' }],
                'label-caps': ['12px', { lineHeight: '16px', letterSpacing: '0.05em', fontWeight: '600' }],
            },
            maxWidth: {
                'container-max': '448px',
            },
        },
    },

    plugins: [forms],
};
