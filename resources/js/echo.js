import Echo from "laravel-echo";

import Pusher from "pusher-js";
window.Pusher = Pusher;

// Determine tenant from URL
const pathSegments = window.location.pathname.split('/').filter(Boolean);
const tenant = pathSegments.length > 0 ? pathSegments[0] : 'arsystem'; // Default or fallback

window.Echo = new Echo({
    broadcaster: "reverb",
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8081,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8081,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
    cluster: 'mt1',
    encrypted: false, // Explicitly disable encryption
    authEndpoint: `/${tenant}/broadcasting/auth`,
});
