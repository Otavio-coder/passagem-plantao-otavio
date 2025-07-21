import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
window.Pusher = Pusher;


window.Echo = new Echo({
    broadcaster: 'pusher',
    key: 'app-key',
    wsHost: 'homologacao05.santacasa.org.br',
    wsPort: import.meta.env.PUSHER_PORT ?? 6001,
    wssPort: import.meta.env.PUSHER_PORT ?? 6001,
    cluster: '',
    forceTLS: true,
    useTLS: true,
    encrypted: true,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
});

