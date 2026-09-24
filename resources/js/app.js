import '../css/site.css';
import './bootstrap';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, h } from 'vue';
import AudienceSwitchHost from './Components/AudienceSwitchHost.vue';
import { ZiggyVue, route } from '../../vendor/tightenco/ziggy';

// Breeze Auth pages call route() inside <script setup>. skip-route-function
// omits route.umd.js from HTML, and `const Ziggy` is not window.Ziggy, so
// expose both from the already-bundled Ziggy client.
if (typeof window !== 'undefined') {
    if (typeof Ziggy !== 'undefined' && !window.Ziggy) {
        window.Ziggy = Ziggy;
    }
    window.route = (name, params, absolute, config) =>
        route(name, params, absolute, config);
}

const errorAppEl = document.getElementById('error-404-app');

if (errorAppEl) {
    // Keep Error404 + particle-field out of the public entry (home unused JS).
    import('./Components/Error404.vue').then(({ default: Error404 }) => {
        createApp(Error404).mount(errorAppEl);
    });
} else {
    createInertiaApp({
        title: (title) => `${title}`,
        resolve: (name) =>
            resolvePageComponent(
                `./Pages/${name}.vue`,
                import.meta.glob('./Pages/**/*.vue'),
            ),
        setup({ el, App, props, plugin }) {
            const app = createApp({
                render: () => [h(App, props), h(AudienceSwitchHost)],
            });

            app.config.errorHandler = (err) => {
                console.error('Inertia error:', err);
            };

            return app
                .use(plugin)
                .use(ZiggyVue)
                .mount(el);
        },
        progress: {
            color: '#4B5563',
        },
    });
}
