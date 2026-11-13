import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type Visit = { onSuccess?: () => void; onError?: () => void }
type FakeForm = { errors: Record<string, string>; [key: string]: unknown }

const { post, reload } = vi.hoisted(() => ({
    post: vi.fn<(form: FakeForm, url: string, options: Visit) => void>(),
    reload: vi.fn(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router: { reload },
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                reset: vi.fn(),
                post: (url: string, options: Visit) => post(form, url, options),
            })
            return form
        },
    }
})

import AssignCycleReviewerDialog from '@/Components/Monev/AssignCycleReviewerDialog.vue'
import type { ReviewerCandidate } from '@/types/submission-manage'

const route = (name: string, params: unknown) => `/${name}/${JSON.stringify(params)}`

const candidate = (overrides: Partial<ReviewerCandidate> = {}): ReviewerCandidate => ({
    reviewerId: 21,
    name: 'Dr. Sari Lestari',
    nidn: '0011223344',
    type: 'internal',
    activeLoad: 2,
    overloaded: false,
    blockedReason: null,
    ...overrides,
})

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find((b) =>
        b.textContent?.includes(label)
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

let wrapper: VueWrapper

const mountDialog = async (candidates: ReviewerCandidate[], suggested: number[] = []) => {
    wrapper = mount(AssignCycleReviewerDialog, {
        props: { open: true, parentId: 7, detailId: 31, suggested, candidates },
        attachTo: document.body,
        global: { mocks: { route } },
    })
    await flushPromises()
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
    reload.mockReset()
})

afterEach(() => wrapper.unmount())

describe('AssignCycleReviewerDialog', () => {
    it('memuat kandidat siklus itu saat dibuka', async () => {
        await mountDialog([])

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({
                only: ['reviewerCandidates'],
                data: { reviewer_cycle: 31, reviewer_search: '' },
            })
        )
        expect(document.body.textContent).toContain(
            'Penilai pengajuan awal yang masih aktif disarankan lebih dulu.'
        )
    })

    it('[MON-REVIEW-06] kandidat saran berlabel Reviewer pengajuan awal dan tampil di atas', async () => {
        await mountDialog([candidate(), candidate({ reviewerId: 5, name: 'Dr. Rina Wijaya' })], [5])
        const names = Array.from(document.querySelectorAll('li')).map((li) => li.textContent ?? '')

        expect(names[0]).toContain('Dr. Rina Wijaya')
        expect(names[0]).toContain('Penilai pengajuan awal')
        expect(names[1]).not.toContain('Penilai pengajuan awal')
    })

    it('[MON-REVIEW-01] satu pilihan dikirim ke monev.manage.reviewers.store', async () => {
        await mountDialog([candidate(), candidate({ reviewerId: 5, name: 'Dr. Rina Wijaya' })])

        button('Dr. Sari Lestari').click()
        await flushPromises()
        button('Dr. Rina Wijaya').click()
        await flushPromises()
        button('Tugaskan').click()
        await flushPromises()

        const [form, url] = post.mock.calls[0]
        expect(url).toBe(route('monev.manage.reviewers.store', { submission: 7, detail: 31 }))
        expect(form.reviewer_id).toBe(5)
    })
})
