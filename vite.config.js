import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            // Evita full-reload por cada cambio en routes/PHP (Inertia no lo necesita).
            // Solo refresca si cambian vistas Blade.
            refresh: ['resources/views/**'],
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve('resources/js'),
        },
    },
    server: {
        watch: {
            // Menos ruido en Windows con editor + agent guardando archivos
            ignored: ['**/storage/**', '**/vendor/**', '**/.git/**'],
        },
    },
});
