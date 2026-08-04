/** Cookie XSRF-TOKEN (encriptada). Enviar solo como header X-XSRF-TOKEN. */
export function getXsrfToken() {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export function syncCsrfMeta(plainToken) {
    if (!plainToken) return;
    let meta = document.head.querySelector('meta[name="csrf-token"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'csrf-token');
        document.head.appendChild(meta);
    }
    meta.setAttribute('content', plainToken);
}
