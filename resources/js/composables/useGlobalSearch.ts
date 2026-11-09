import { computed, getCurrentScope, onScopeDispose, ref, watch } from 'vue'

import { fetchGlobalSearch } from '@/lib/globalSearch'
import type { GlobalSearchResult } from '@/types/search'

export const SEARCH_MIN_LENGTH = 2
export const SEARCH_DEBOUNCE_MS = 300

function emptyResult(): GlobalSearchResult {
    return { submissions: [], users: [], reviewers: [] }
}

/**
 * K2 — grup server command palette (topbar.md): debounce 300 ms, dan setiap istilah baru
 * membatalkan request sebelumnya sehingga respons lama tidak menimpa hasil istilah terbaru.
 */
export function useGlobalSearch() {
    const term = ref('')
    const results = ref<GlobalSearchResult>(emptyResult())
    const loading = ref(false)
    const failed = ref(false)
    const tooShort = computed(() => term.value.trim().length < SEARCH_MIN_LENGTH)

    let timer: ReturnType<typeof setTimeout> | undefined
    let inFlight: AbortController | null = null

    function cancel() {
        clearTimeout(timer)
        inFlight?.abort()
        inFlight = null
    }

    async function search(query: string) {
        const request = new AbortController()
        inFlight = request

        try {
            const found = await fetchGlobalSearch(query, request.signal)
            if (inFlight !== request) return
            results.value = found
            failed.value = false
        } catch {
            // Dibatalkan atau sudah digantikan istilah yang lebih baru: bukan kegagalan.
            if (inFlight !== request) return
            results.value = emptyResult()
            failed.value = true
        }

        inFlight = null
        loading.value = false
    }

    watch(term, (value) => {
        cancel()
        const query = value.trim()

        if (query.length < SEARCH_MIN_LENGTH) {
            results.value = emptyResult()
            failed.value = false
            loading.value = false

            return
        }

        loading.value = true
        timer = setTimeout(() => void search(query), SEARCH_DEBOUNCE_MS)
    })

    if (getCurrentScope()) onScopeDispose(cancel)

    return { term, results, loading, failed, tooShort }
}
