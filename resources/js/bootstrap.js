import axios from 'axios';
import { getXsrfToken, syncCsrfMeta } from './csrf';

window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';

export { getXsrfToken, syncCsrfMeta };

/** Alias: actualiza el meta con el token plano de sesión (props.csrf_token). */
export const syncCsrfToken = syncCsrfMeta;

/**
 * Token plano del meta (sincronizado tras cada visita Inertia).
 * No devolver la cookie aquí: la cookie es cifrada y solo vale como X-XSRF-TOKEN.
 */
export function getCsrfToken() {
    return document.head.querySelector('meta[name="csrf-token"]')?.content || '';
}
