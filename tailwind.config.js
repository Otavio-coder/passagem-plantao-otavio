import forms from '@tailwindcss/forms';

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

    plugins: [forms],
};
