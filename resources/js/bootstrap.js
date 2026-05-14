import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
// window.axios.defaults.withCredentials = true;

window.axios.interceptors.request.use((config) => {
    const requestUrl = config?.url || "";
    let origin = "";

    try {
        origin = new URL(requestUrl, window.location.origin).origin;
    } catch (e) {
        origin = window.location.origin;
    }

    if (origin === window.location.origin) {
        const csrfToken = document.head.querySelector('meta[name="csrf-token"]')?.content;
        if (csrfToken) {
            config.headers = config.headers || {};
            config.headers["X-CSRF-TOKEN"] = csrfToken;
        }
    }

    return config;
});

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allow your team to quickly build robust real-time web applications.
 */
