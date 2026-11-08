import { router } from '@inertiajs/vue3'
import { reactive, watch } from 'vue'

const SEARCH_DEBOUNCE_MS = 300

/**
 * Filter tabel yang disaring server (fe-v2-design §4.2). `page` selalu dibuang
 * saat filter berubah: halaman 3 dari hasil lama bisa kosong di hasil baru.
 * `routeParams` menahan segmen route, mis. tab statistik (W156).
 */
export function useFilters<T extends Record<string, string>>(
    routeName: string,
    initial: T,
    routeParams?: Record<string, string>
) {
    const filters = reactive({ ...initial })
    let timer: ReturnType<typeof setTimeout> | undefined

    const visit = () => {
        const params = Object.fromEntries(
            Object.entries(filters).filter(([key, value]) => key !== 'page' && value !== '')
        )
        router.get(routeParams ? route(routeName, routeParams) : route(routeName), params, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
        })
    }

    let lastSearch = filters.search
    watch(
        filters,
        () => {
            clearTimeout(timer)
            const searchChanged = filters.search !== lastSearch
            lastSearch = filters.search
            if (!searchChanged) {
                visit()
                return
            }
            timer = setTimeout(visit, SEARCH_DEBOUNCE_MS)
        },
        { deep: true }
    )

    return { filters }
}
