import { describe, expect, it, vi } from 'vitest'

// vi.mock diangkat ke puncak berkas, jadi mock-nya harus dibuat lewat
// vi.hoisted agar `post` sudah ada saat factory dijalankan.
const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post, visit: vi.fn() },
    useForm: () => ({ reset: vi.fn() }),
    Head: {},
}))

import { ensureSubmissionStarted } from '@/Pages/User/FormPhase.vue'

const url = '/user/submissions'

describe('ensureSubmissionStarted', () => {
    it('memanggil endpoint langkah 1 sekali untuk satu Form', () => {
        post.mockClear()
        const started = new Set<number>()

        ensureSubmissionStarted(started, post, url, { formId: 7, periodId: 3, phaseId: 5 })
        ensureSubmissionStarted(started, post, url, { formId: 7, periodId: 3, phaseId: 5 })

        expect(post).toHaveBeenCalledTimes(1)
        expect(post).toHaveBeenCalledWith(
            url,
            { form_id: 7, submission_period_id: 3, form_phase_id: 5 },
            { preserveState: true, preserveScroll: true }
        )
    })

    it('memanggilnya lagi saat Form berganti', () => {
        post.mockClear()
        const started = new Set<number>()

        ensureSubmissionStarted(started, post, url, { formId: 7, periodId: 3, phaseId: 5 })
        ensureSubmissionStarted(started, post, url, { formId: 8, periodId: 3, phaseId: 5 })

        expect(post).toHaveBeenCalledTimes(2)
    })
})
