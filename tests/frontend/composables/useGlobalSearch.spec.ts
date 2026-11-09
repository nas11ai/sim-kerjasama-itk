import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { effectScope, nextTick } from 'vue'

import type { GlobalSearchResult } from '@/types/search'

const fetchGlobalSearch =
    vi.fn<(term: string, signal: AbortSignal) => Promise<GlobalSearchResult>>()
vi.mock('@/lib/globalSearch', () => ({
    fetchGlobalSearch: (term: string, signal: AbortSignal) => fetchGlobalSearch(term, signal),
}))

const { useGlobalSearch, SEARCH_DEBOUNCE_MS } = await import('@/composables/useGlobalSearch')

const EMPTY: GlobalSearchResult = { submissions: [], users: [], reviewers: [] }

function hasil(label: string): GlobalSearchResult {
    return { ...EMPTY, users: [{ id: 1, label, sublabel: '', url: '/u/1' }] }
}

interface Pending {
    resolve: (r: GlobalSearchResult) => void
    reject: (e: unknown) => void
}

function pending(): Pending {
    const handle = {} as Pending
    fetchGlobalSearch.mockImplementationOnce(
        () =>
            new Promise<GlobalSearchResult>((resolve, reject) => {
                handle.resolve = resolve
                handle.reject = reject
            })
    )

    return handle
}

let scope: ReturnType<typeof effectScope>

function mount() {
    scope = effectScope()

    return scope.run(() => useGlobalSearch())!
}

async function ketik(search: ReturnType<typeof useGlobalSearch>, value: string) {
    search.term.value = value
    await nextTick()
}

async function flush() {
    await Promise.resolve()
    await Promise.resolve()
    await nextTick()
}

beforeEach(() => {
    vi.useFakeTimers()
    fetchGlobalSearch.mockReset()
})

afterEach(() => {
    scope.stop()
    vi.useRealTimers()
})

describe('useGlobalSearch', () => {
    it('istilah 1 huruf tidak memanggil server dan tooShort', async () => {
        const search = mount()

        await ketik(search, 'b')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS * 2)

        expect(fetchGlobalSearch).not.toHaveBeenCalled()
        expect(search.tooShort.value).toBe(true)
    })

    it('dua ketukan dalam 300 ms menjadi satu fetch', async () => {
        fetchGlobalSearch.mockResolvedValue(hasil('Budi'))
        const search = mount()

        await ketik(search, 'bu')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS - 100)
        await ketik(search, 'bud')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)
        await flush()

        expect(fetchGlobalSearch).toHaveBeenCalledTimes(1)
        expect(fetchGlobalSearch.mock.calls[0][0]).toBe('bud')
        expect(search.results.value).toEqual(hasil('Budi'))
        expect(search.loading.value).toBe(false)
        expect(search.tooShort.value).toBe(false)
    })

    it('istilah baru membatalkan request lama dan hasil lama tidak menimpa', async () => {
        const lama = pending()
        const baru = pending()
        const search = mount()

        await ketik(search, 'bu')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)
        await ketik(search, 'budi')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)

        const signalLama = fetchGlobalSearch.mock.calls[0][1]
        expect(signalLama.aborted).toBe(true)

        baru.resolve(hasil('Budi Santoso'))
        await flush()
        lama.resolve(hasil('Bu Lama'))
        await flush()

        expect(search.results.value).toEqual(hasil('Budi Santoso'))
        expect(search.failed.value).toBe(false)
    })

    it('request yang dibatalkan tidak dihitung gagal', async () => {
        const lama = pending()
        fetchGlobalSearch.mockResolvedValueOnce(hasil('Budi'))
        const search = mount()

        await ketik(search, 'bu')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)
        await ketik(search, 'budi')
        lama.reject(new DOMException('aborted', 'AbortError'))
        await flush()

        expect(search.failed.value).toBe(false)
    })

    it('fetch gagal → failed, hasil kosong, tanpa throw', async () => {
        fetchGlobalSearch.mockRejectedValue(new Error('419'))
        const search = mount()

        await ketik(search, 'budi')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)
        await flush()

        expect(search.failed.value).toBe(true)
        expect(search.results.value).toEqual(EMPTY)
        expect(search.loading.value).toBe(false)
    })

    it('istilah dikosongkan → hasil kosong dan failed false', async () => {
        fetchGlobalSearch.mockRejectedValueOnce(new Error('jaringan'))
        const search = mount()

        await ketik(search, 'budi')
        vi.advanceTimersByTime(SEARCH_DEBOUNCE_MS)
        await flush()
        await ketik(search, '')

        expect(search.results.value).toEqual(EMPTY)
        expect(search.failed.value).toBe(false)
        expect(search.loading.value).toBe(false)
    })
})
