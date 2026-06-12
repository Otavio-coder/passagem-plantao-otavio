import forms from '@tailwindcss/forms';
import typography from '@tailwindcss/typography';

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
        "./resources/**/*.vue",
        "./public/js/**/*.js",
    ],

    theme: {
        extend: {
            fontFamily: {
                mono: ['Montserrat', 'sans-serif'],
            },
            colors: {
                'santacasa': {
                    100: '#0071B9',
                    200: '#004D9D',
                    300: '#062047',
                    default: '#073772'
                }
            },
        },
    },

    plugins: [forms, typography],
};
