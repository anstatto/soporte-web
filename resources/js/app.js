import './bootstrap';
import './echo';
import { syncCsrfMeta } from './csrf';

import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import ElementPlus from 'element-plus';
import es from 'element-plus/es/locale/lang/es';
import 'element-plus/dist/index.css';
import 'element-plus/theme-chalk/dark/css-vars.css';
import '../css/app.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import AppLayout from './Layouts/AppLayout.vue';
import GuestLayout from './Layouts/GuestLayout.vue';

const appName = import.meta.env.VITE_APP_NAME || 'RM Consuegra Soporte';

// Tras login/logout Inertia no recarga el HTML: sincronizar meta csrf evita 419.
const refreshCsrfFromPage = (page) => {
    const token = page?.props?.csrf_token;
    if (token) syncCsrfMeta(token);
};

router.on('success', (event) => {
    refreshCsrfFromPage(event.detail.page);
});

router.on('navigate', (event) => {
    refreshCsrfFromPage(event.detail.page);
});

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')).then((module) => {
            const page = module.default;
            // Layout persistente: el shell no se remonta al navegar (SPA real)
            if (page.layout === undefined) {
                page.layout = name.startsWith('Auth/') ? GuestLayout : AppLayout;
            }
            return module;
        }),
    setup({ el, App, props, plugin }) {
        refreshCsrfFromPage(props.initialPage);
        const app = createApp({ render: () => h(App, props) });
        app.use(plugin);
        app.use(ElementPlus, { locale: es, size: 'default', zIndex: 3000 });
        for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
            app.component(key, component);
        }
        app.mount(el);
    },
    progress: {
        color: '#579DFF',
        showSpinner: false,
        delay: 150,
    },
});

// PWA: registrar service worker (instalable en escritorio/móvil)
if (import.meta.env.PROD && 'serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {});
    });
}
