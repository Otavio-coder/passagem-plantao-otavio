import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import fs from 'fs';
import dotenv from 'dotenv';

dotenv.config();

export default defineConfig(({ command }) => {
    const host = process.env.VITE_ASSET_HOST;
    const port = parseInt(process.env.VITE_ASSET_PORT || '5173', 10);
    const keyPath = process.env.VITE_PRIVKEY_PATH;
    const certPath = process.env.VITE_CERT_PATH;

    const server = {
        host: true,
        port,
        strictPort: true,
        cors: true,
        hmr: { host, port },
    };

    if (command === 'serve' && keyPath && certPath && fs.existsSync(keyPath) && fs.existsSync(certPath)) {
        server.https = {
            key: fs.readFileSync(keyPath),
            cert: fs.readFileSync(certPath),
        };
    }

    return {
        server,
        plugins: [
            vue(),
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/patient-modal.js', 'resources/js/chat-component-global.js'],
                refresh: true,
            }),
        ],
    };
});