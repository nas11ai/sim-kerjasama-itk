import { nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({ router: { reload } }))

import { usePartialSearch } from '@/composables/usePartialSearch'

beforeEach(() => {
    vi.useFakeTimers()
    reload.mockReset()
})

afterEach(() => vi.useRealTimers())

describe('usePartialSearch', () => {
    it('memuat prop yang diminta lewat partial reload dengan parameter pencarian', () => {
        const { load } = usePartialSearch('appointmentCandidates', 'candidate_search')

        load()

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['appointmentCandidates'],
                data: { candidate_search: '' },
            })
        )
    })

    it('pencarian di-debounce 300 ms', async () => {
        const { search } = usePartialSearch('appointmentCandidates', 'candidate_search')

        search.value = 'ri'
        await nextTick()
        search.value = 'rina'
        await nextTick()
        vi.advanceTimersByTime(299)
        expect(reload).not.toHaveBeenCalled()

        vi.advanceTimersByTime(1)

        expect(reload).toHaveBeenCalledTimes(1)
        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ data: { candidate_search: 'rina' } })
        )
    })
})
