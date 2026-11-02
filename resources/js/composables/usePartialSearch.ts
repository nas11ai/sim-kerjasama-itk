import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const SEARCH_DEBOUNCE_MS = 300

// Pencarian lewat partial reload satu prop `only`, jadi penyaringan tetap di server.
export function usePartialSearch(only: string, param: string) {
    const search = ref('')
    let timer: ReturnType<typeof setTimeout> | undefined

    const load = () =>
        router.reload({
            only: [only],
            data: { [param]: search.value },
        })

    watch(search, () => {
        clearTimeout(timer)
        timer = setTimeout(load, SEARCH_DEBOUNCE_MS)
    })

    return { search, load }
}
