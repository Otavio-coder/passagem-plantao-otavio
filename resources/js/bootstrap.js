// bootstrap.js - Configuração simplificada do Echo

import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;

// Configuração do Echo
window.Echo = new Echo({
    broadcaster: 'pusher',
    key: 'app-key',
    wsHost: 'homologacao05.santacasa.org.br',
    wsPort: 6001,
    wssPort: 6001,
    cluster: '',
    forceTLS: true,
    encrypted: true,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    authEndpoint: '/broadcasting/auth',
    auth: {
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
    },
});

// Log de conexão simplificado
window.Echo.connector.pusher.connection.bind('connected', () => {
    // Carrega o sistema de chat
    import('./chat-echo-listeners.js').then(() => {
        //console.log('[Echo] Sistema de chat carregado');
    });
});

// Log de erros de conexão
window.Echo.connector.pusher.connection.bind('error', (error) => {
    //console.error('[Echo] Erro de conexão:', error);
});

// Log de estado da conexão
window.Echo.connector.pusher.connection.bind('state_change', (states) => {
    //console.log('[Echo] Estado:', states.previous, '→', states.current);
});


// PWA install prompt (mantém como estava)
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    window.deferredPrompt = e;

    if (document.getElementById('pwa-install-btn')) return;

    const btnContainer = document.createElement('div');
    btnContainer.id = 'pwa-install-btn';
    btnContainer.className = `
        fixed top-[100px] right-4 z-[99999]
        flex flex-col items-end pointer-events-auto
        w-auto sm:right-6 md:right-10 lg:right-16
    `;

    const btn = document.createElement('button');
    btn.className = `
        bg-[#004D9D] text-white font-semibold px-2 py-1 rounded-full shadow-lg
        flex items-center gap-1 transition hover:bg-[#0071B9]
        text-[0.75rem] sm:text-sm md:text-base
        min-w-[120px] sm:min-w-[140px] md:min-w-[160px]
        relative
    `;
    btn.innerHTML = `
        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Instalar Passagem de Plantão
    `;
    btn.onclick = function () {
        window.deferredPrompt.prompt();
        btnContainer.remove();
    };

    const closeBtn = document.createElement('button');
    closeBtn.className = `
        absolute -top-2 -right-2 bg-white text-gray-500 rounded-full p-1 shadow
        hover:text-gray-700 transition
        flex items-center justify-center
    `;
    closeBtn.innerHTML = `
        <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
    `;
    closeBtn.onclick = function () {
        btnContainer.remove();
    };

    btnContainer.appendChild(btn);
    btnContainer.appendChild(closeBtn);
    document.body.appendChild(btnContainer);
});