import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/sass/app.scss', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        // host 0.0.0.0 permite que Vite acepte conexiones desde fuera del
        // contenedor Docker. laravel-vite-plugin v3 usa ese valor tal cual
        // al escribir "public/hot", así que sin hmr.host el navegador
        // intentaría pedir los assets a http://0.0.0.0:5174 (no enrutable).
        // hmr.host fuerza que public/hot y el cliente HMR usen localhost.
        host: '0.0.0.0',
        port: 5174,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
    },
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
