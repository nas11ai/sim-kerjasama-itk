import '../css/app.css'
import './bootstrap'

import { createInertiaApp, router } from '@inertiajs/vue3'
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers'
import { createApp, DefineComponent, h } from 'vue'
import { ZiggyVue } from 'ziggy-js'

import { syncAccountChannel } from '@/lib/accountChannel'

const appName = import.meta.env.VITE_APP_NAME || 'Laravel'

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue')
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el)

        // RT1 — kanal user mengikuti siapa yang login, termasuk login/logout tanpa muat ulang.
        syncAccountChannel(props.initialPage.props.auth.user?.id ?? null)
        router.on('navigate', (event) =>
            syncAccountChannel(event.detail.page.props.auth.user?.id ?? null)
        )
    },
    progress: {
        color: '#4B5563',
    },
})
