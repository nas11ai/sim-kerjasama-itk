import { router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'

const SEARCH_DEBOUNCE_MS = 300

// Kandidat reviewer lewat partial reload prop `reviewerCandidates`, jadi pencarian tetap di server.
export function useReviewerCandidates() {
    const search = ref('')
    let timer: ReturnType<typeof setTimeout> | undefined

    const load = () =>
        router.reload({
            only: ['reviewerCandidates'],
            data: { reviewer_search: search.value },
        })

    watch(search, () => {
        clearTimeout(timer)
        timer = setTimeout(load, SEARCH_DEBOUNCE_MS)
    })

    return { search, load }
}
