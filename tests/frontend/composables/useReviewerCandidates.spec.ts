import { nextTick } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({ router: { reload } }))

import { useReviewerCandidates } from '@/composables/useReviewerCandidates'

beforeEach(() => {
    vi.useFakeTimers()
    reload.mockReset()
})

afterEach(() => vi.useRealTimers())

describe('useReviewerCandidates', () => {
    it('memuat kandidat lewat partial reload', () => {
        const { load } = useReviewerCandidates()

        load()

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['reviewerCandidates'],
                data: { reviewer_search: '' },
            })
        )
    })

    it('pencarian di-debounce', async () => {
        const { search } = useReviewerCandidates()

        search.value = 'sa'
        await nextTick()
        search.value = 'sari'
        await nextTick()
        vi.advanceTimersByTime(299)
        expect(reload).not.toHaveBeenCalled()

        vi.advanceTimersByTime(1)

        expect(reload).toHaveBeenCalledTimes(1)
        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ data: { reviewer_search: 'sari' } })
        )
    })
})
