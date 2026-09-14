import { createInertiaApp } from '@inertiajs/vue3'
import createServer from '@inertiajs/vue3/server'
import { renderToString } from '@vue/server-renderer'
import { createSSRApp, h } from 'vue'
import { ZiggyVue } from '../../vendor/tightenco/ziggy'
import { Ziggy } from './ziggy'

createServer((page) =>
    createInertiaApp({
        page,
        render: renderToString,
        resolve: (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue', { eager: true })
            return pages[`./Pages/${name}.vue`]
        },
        setup({ App, props, plugin }) {
            // SSR: ZiggyVue нужен полный конфиг маршрутов, иначе route('blog.authors') падает
            const ziggyConfig = {
                ...Ziggy,
                location: new URL(page.url || '/', Ziggy.url || 'https://alexallergotest.ru'),
            }
            return createSSRApp({ render: () => h(App, props) })
                .use(plugin)
                .use(ZiggyVue, ziggyConfig)
        },
    })
)
