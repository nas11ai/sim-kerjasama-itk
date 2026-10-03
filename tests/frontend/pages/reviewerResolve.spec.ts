import { describe, expect, it, vi } from 'vitest'

// vi.mock diangkat ke puncak berkas, jadi mock-nya harus dibuat lewat
// vi.hoisted agar `post` sudah ada saat factory dijalankan.
const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post, visit: vi.fn() },
    Head: {},
}))

vi.mock('ziggy-js', () => ({
    route: (name: string, id?: number) => `${name}:${id}`,
}))

import {
    resolvableSummaryId,
    resolveReviewSummary,
} from '@/Pages/Reviewer/Submissions/ShowPage.vue'

describe('resolvableSummaryId', () => {
    it('menunjuk ulir yang masih terbuka', () => {
        expect(resolvableSummaryId({ id: 7, status: 'open' })).toBe(7)
    })

    it('tidak menawarkan apa pun untuk ulir yang sudah ditutup', () => {
        expect(resolvableSummaryId({ id: 7, status: 'resolved' })).toBeNull()
    })

    it('tidak menawarkan apa pun saat reviewer belum punya ulir', () => {
        expect(resolvableSummaryId(undefined)).toBeNull()
    })
})

describe('resolveReviewSummary', () => {
    it('menutup ulir lewat alur resolve, bukan rute status yang tidak terdaftar', () => {
        post.mockClear()

        resolveReviewSummary(post, 7)

        expect(post).toHaveBeenCalledTimes(1)
        expect(post.mock.calls[0][0]).toBe('review-summaries.resolve:7')
        expect(post.mock.calls[0][1]).toEqual({})
    })
})
