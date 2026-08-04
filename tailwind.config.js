import defaultTheme from 'tailwindcss/defaultTheme';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{js,vue}',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    navy: '#15191E',
                    primary: '#579DFF',
                    accent: '#85B8FF',
                    surface: '#15191E',
                    column: '#1D2329',
                    card: '#252C34',
                    border: '#343C45',
                    muted: '#8B9AAB',
                },
                state: {
                    success: '#3D7A5F',
                    warning: '#B7791F',
                    danger: '#C4554D',
                },
                chat: {
                    mine: '#252C34',
                    other: '#1D2329',
                },
            },
            fontFamily: {
                sans: ['"Source Sans 3"', ...defaultTheme.fontFamily.sans],
                display: ['"IBM Plex Sans"', ...defaultTheme.fontFamily.sans],
            },
        },
    },
    plugins: [],
};
