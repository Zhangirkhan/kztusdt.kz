import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                accent: '#2563eb',
                'accent-bright': '#0052ff',
                'on-accent': '#ffffff',
                background: '#eef1f6',
                surface: '#ffffff',
                'surface-container': '#f8fafc',
                'surface-container-low': '#f1f5f9',
                'surface-container-lowest': '#ffffff',
                'surface-container-high': '#e2e8f0',
                'on-surface': '#0f172a',
                'on-surface-variant': '#64748b',
                'text-muted': '#64748B',
                'text-dim': '#94A3B8',
                'outline-variant': '#e2e8f0',
                'input-border': '#e2e8f0',
                primary: '#2563eb',
                'primary-dark': '#1d4ed8',
                'primary-light': '#eff6ff',
                error: '#ef4444',
                success: '#22c55e',
                warning: '#f59e0b',
            },
            borderRadius: {
                xl: '12px',
                '2xl': '16px',
                '3xl': '20px',
            },
            spacing: {
                'container-max': '390px',
                'margin-page': '24px',
                'input-padding': '16px',
                'stack-section': '24px',
                'stack-element': '16px',
                'bottom-nav': '80px',
            },
            fontFamily: {
                sans: ['Manrope', ...defaultTheme.fontFamily.sans],
            },
            fontSize: {
                'headline-xl': ['30px', { lineHeight: '36px', fontWeight: '700', letterSpacing: '-0.02em' }],
                'headline-md': ['18px', { lineHeight: '24px', fontWeight: '700', letterSpacing: '-0.01em' }],
                'body-lg': ['16px', { lineHeight: '24px', fontWeight: '400' }],
                'body-sm': ['14px', { lineHeight: '20px', fontWeight: '400' }],
                'label-caps': ['11px', { lineHeight: '16px', letterSpacing: '0.06em', fontWeight: '600' }],
            },
            maxWidth: {
                'container-max': '390px',
            },
            boxShadow: {
                sm: '0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)',
                md: '0 4px 24px rgba(15, 23, 42, 0.08)',
                btn: '0 4px 14px rgba(37, 99, 235, 0.35)',
                fab: '0 4px 20px rgba(0, 82, 255, 0.35)',
                card: '0 1px 3px rgba(15, 23, 42, 0.08), 0 1px 2px rgba(15, 23, 42, 0.04)',
                nav: '0 -4px 24px rgba(15, 23, 42, 0.06)',
            },
        },
    },

    plugins: [forms],
};
