import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'
import fs from 'fs';
import dotenv from 'dotenv';

dotenv.config();
const host = process.env.VITE_ASSET_HOST;
const port = parseInt(process.env.VITE_ASSET_PORT);
const key = process.env.VITE_PRIVKEY_PATH;
const cert = process.env.VITE_CERT_PATH;
export default defineConfig({
    server: {
        host: true,
        port: port,
        strictPort: true,
        hmr: {
            host: host,
            port: port,
        },
        https: {
            key: fs.readFileSync(key),
            cert: fs.readFileSync(cert),
        },
    },
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/patient-modal.js', 'resources/js/chat-component-global.js', 'resources/js/content-protection.js'],
            refresh: true,
        }),
    ],
});
