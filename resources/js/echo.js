import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import axios from 'axios';

window.Pusher = Pusher;

const key = import.meta.env.VITE_REVERB_APP_KEY;
const host = import.meta.env.VITE_REVERB_HOST || '127.0.0.1';
const port = Number(import.meta.env.VITE_REVERB_PORT || 8080);
const scheme = import.meta.env.VITE_REVERB_SCHEME || 'http';

if (key) {
    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: host,
        wsPort: port,
        wssPort: port,
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
        authEndpoint: '/broadcasting/auth',
        withCredentials: true,
        authorizer: (channel) => ({
            authorize: (socketId, callback) => {
                axios
                    .post('/broadcasting/auth', {
                        socket_id: socketId,
                        channel_name: channel.name,
                    })
                    .then((response) => callback(false, response.data))
                    .catch((error) => {
                        console.warn('[Echo] auth falló', channel.name, error?.response?.status || error);
                        callback(true, error);
                    });
            },
        }),
    });

    window.Echo.connector?.pusher?.connection?.bind('connected', () => {
        if (import.meta.env.DEV) console.info('[Echo] WebSocket conectado', `${host}:${port}`);
    });
    window.Echo.connector?.pusher?.connection?.bind('error', (err) => {
        console.warn('[Echo] error de conexión', err);
    });
} else {
    console.warn('[Echo] VITE_REVERB_APP_KEY vacío — Reverb desactivado');
    window.Echo = null;
}

export default window.Echo;
