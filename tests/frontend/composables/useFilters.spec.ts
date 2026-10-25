import { effectScope, nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { get } = vi.hoisted(() => ({ get: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({ router: { get } }))

import { useFilters } from '@/composables/useFilters'

const options = { preserveState: true, replace: true, preserveScroll: true }

let scope: ReturnType<typeof effectScope>

const start = <T extends Record<string, string>>(initial: T) => {
    scope = effectScope()
    const result = scope.run(() => useFilters('user.submissions.index', initial))
    if (result === undefined) throw new Error('scope tidak berjalan')
    return result.filters
}

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`)
    vi.useFakeTimers()
})

afterEach(() => {
    scope.stop()
    get.mockReset()
    vi.useRealTimers()
})

describe('useFilters', () => {
    it('tidak memanggil router saat setup', async () => {
        start({ search: '', status: 'draft' })
        await nextTick()
        vi.runAllTimers()

        expect(get).not.toHaveBeenCalled()
    })

    it('mengirim filter yang berubah dengan state dipertahankan', async () => {
        const filters = start({ search: '', status: '' })

        filters.status = 'draft'
        await nextTick()

        expect(get).toHaveBeenCalledWith('/user.submissions.index', { status: 'draft' }, options)
    })

    it('membuang filter kosong', async () => {
        const filters = start({ search: 'lama', status: '' })

        filters.search = ''
        filters.status = 'draft'
        await nextTick()
        vi.runAllTimers()

        expect(get).toHaveBeenLastCalledWith(
            '/user.submissions.index',
            { status: 'draft' },
            options
        )
    })

    it('membuang page saat filter berubah', async () => {
        const filters = start({ status: '', page: '3' })

        filters.status = 'approved'
        await nextTick()

        expect(get).toHaveBeenCalledWith('/user.submissions.index', { status: 'approved' }, options)
    })

    it('search di-debounce 300 ms', async () => {
        const filters = start({ search: '', status: '' })

        filters.search = 'bio'
        await nextTick()
        filters.search = 'biochar'
        await nextTick()
        vi.advanceTimersByTime(299)
        expect(get).not.toHaveBeenCalled()

        vi.advanceTimersByTime(1)
        expect(get).toHaveBeenCalledTimes(1)
        expect(get).toHaveBeenCalledWith('/user.submissions.index', { search: 'biochar' }, options)
    })
})
