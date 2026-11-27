import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

const token = (name) => `rgb(var(--color-${name}) / <alpha-value>)`;

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                primary: {
                    50: token('primary-50'),
                    100: token('primary-100'),
                    200: token('primary-200'),
                    400: token('primary-400'),
                    500: token('primary-500'),
                    600: token('primary-600'),
                    700: token('primary-700'),
                    800: token('primary-800'),
                },
                sidebar: {
                    DEFAULT: token('sidebar'),
                    hover: token('sidebar-hover'),
                    line: token('sidebar-line'),
                    text: token('sidebar-text'),
                    muted: token('sidebar-muted'),
                },
                info: { 50: token('info-50'), 600: token('info-600'), 800: token('info-800') },
                danger: {
                    50: token('danger-50'),
                    500: token('danger-500'),
                    600: token('danger-600'),
                    700: token('danger-700'),
                },
                success: {
                    50: token('success-50'),
                    600: token('success-600'),
                    800: token('success-800'),
                },
                warning: {
                    50: token('warning-50'),
                    600: token('warning-600'),
                    800: token('warning-800'),
                },
                surface: token('surface'),
                card: token('card'),
                line: { DEFAULT: token('line'), strong: token('line-strong') },
                ink: token('ink'),
                muted: token('muted'),
            },
        },
    },

    plugins: [forms],
};
