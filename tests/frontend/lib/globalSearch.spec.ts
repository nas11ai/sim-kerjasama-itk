import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import { fetchGlobalSearch } from '@/lib/globalSearch'

const route = vi.fn((name: string, params?: Record<string, string>) => `/${name}?q=${params?.q}`)
const fetchMock = vi.fn()

beforeEach(() => {
    vi.stubGlobal('route', route)
    vi.stubGlobal('fetch', fetchMock)
    route.mockClear()
    fetchMock.mockReset()
})

afterEach(() => {
    vi.unstubAllGlobals()
})

const RESULT = {
    submissions: [],
    users: [{ id: 1, label: 'Budi', sublabel: '', url: '/u/1' }],
    reviewers: [],
}

describe('fetchGlobalSearch', () => {
    it('meminta route search sebagai JSON bersesi dan tidak mengikuti pengalihan', async () => {
        fetchMock.mockResolvedValue(new Response(JSON.stringify(RESULT), { status: 200 }))
        const signal = new AbortController().signal

        await expect(fetchGlobalSearch('budi', signal)).resolves.toEqual(RESULT)

        expect(route).toHaveBeenCalledWith('search', { q: 'budi' })
        expect(fetchMock).toHaveBeenCalledWith('/search?q=budi', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
            redirect: 'error',
            signal,
        })
    })

    it('status selain 2xx (mis. 419 sesi habis) ditolak', async () => {
        fetchMock.mockResolvedValue(new Response('', { status: 419 }))

        await expect(fetchGlobalSearch('budi', new AbortController().signal)).rejects.toThrow(Error)
    })
})
