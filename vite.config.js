import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // UIkit todavía usa @import en su propio SCSS; silenciamos solo el aviso
                // de "@import está obsoleto" para que la salida de la build quede legible.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
            },
        },
    },
});
