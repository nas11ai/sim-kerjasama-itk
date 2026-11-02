import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type Visit = { onSuccess?: () => void; onError?: () => void; onFinish?: () => void }
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

import AssignReviewersDialog from '@/Components/Submission/Manage/AssignReviewersDialog.vue'
import ReplaceReviewerDialog from '@/Components/Submission/Manage/ReplaceReviewerDialog.vue'
import type { ManagedReviewer, ReviewerCandidate } from '@/types/submission-manage'

const route = (name: string, params: unknown) => `/${name}/${String(params)}`

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

const outgoing: ManagedReviewer = {
    reviewerId: 5,
    name: 'Dr. Rina Wijaya',
    nidn: '0099887766',
    type: 'external',
    replaced: false,
    evaluationLabel: 'Evaluasi berjalan',
    accountActive: true,
}

const text = () => document.body.textContent ?? ''

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find((b) =>
        b.textContent?.includes(label)
    )
    if (found === undefined) {
        throw new Error(`tombol "${label}" tidak ada`)
    }
    return found
}

const click = async (label: string) => {
    button(label).click()
    await flushPromises()
}

let wrapper: VueWrapper

const mountDialog = async (component: object, props: Record<string, unknown>) => {
    wrapper = mount(component, {
        props: { open: true, submissionId: 9, ...props },
        attachTo: document.body,
        global: { mocks: { route } },
    })
    await flushPromises()
}

const assign = (candidates: ReviewerCandidate[]) =>
    mountDialog(AssignReviewersDialog, { activeCount: 1, minimum: 2, candidates })

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
    reload.mockReset()
})

afterEach(() => wrapper.unmount())

describe('AssignReviewersDialog', () => {
    it('memuat kandidat saat dibuka', async () => {
        await assign([])

        expect(reload).toHaveBeenCalledWith(
            expect.objectContaining({ only: ['reviewerCandidates'] })
        )
    })

    it('[REV-ASSIGN-01] satu reviewer sudah bisa ditugaskan', async () => {
        await assign([candidate()])

        expect(button('Tugaskan').disabled).toBe(true)
        await click('Dr. Sari Lestari')
        expect(button('Tugaskan').disabled).toBe(false)
        await click('Tugaskan')

        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.assign-reviewers/9')
        expect(form.reviewer_ids).toEqual([21])
        expect(form.auto_assign_forms).toBe(true)
    })

    it('[REV-ASSIGN-02] indikator menghitung reviewer aktif dan pilihan', async () => {
        await assign([candidate()])

        expect(text()).toContain('1 dari minimal 2')
        await click('Dr. Sari Lestari')

        expect(text()).toContain('2 dari minimal 2')
    })

    it('[REV-ASSIGN-04] kandidat konflik tidak bisa dipilih', async () => {
        await assign([candidate({ blockedReason: 'Konflik kepentingan' })])

        expect(text()).toContain('Konflik kepentingan')
        expect(button('Dr. Sari Lestari').disabled).toBe(true)
    })

    it('[REV-ASSIGN-06] kandidat berbeban tinggi tetap bisa dipilih', async () => {
        await assign([candidate({ overloaded: true, activeLoad: 9 })])

        expect(text()).toContain('Beban Tinggi')
        expect(text()).toContain('Workload: 9 aktif')
        expect(button('Dr. Sari Lestari').disabled).toBe(false)
    })

    it('galat penugasan tampil dan dialog tetap terbuka', async () => {
        post.mockImplementation((form, _url, options) => {
            form.errors = { reviewer_id: 'Reviewer ini sudah ditugaskan pada siklus review ini.' }
            options.onError?.()
        })
        await assign([candidate()])

        await click('Dr. Sari Lestari')
        await click('Tugaskan')

        expect(text()).toContain('Reviewer ini sudah ditugaskan pada siklus review ini.')
        expect(wrapper.emitted('update:open')).toBeUndefined()
    })

    it('kandidat dimuat ulang setelah galat penugasan', async () => {
        // Respons galat penuh tidak membawa prop opsional reviewerCandidates.
        post.mockImplementation((form, _url, options) => {
            form.errors = { user_id: 'Konflik kepentingan.' }
            options.onError?.()
        })
        await assign([candidate()])

        await click('Dr. Sari Lestari')
        await click('Tugaskan')

        expect(reload).toHaveBeenCalledTimes(2)
    })
})

describe('ReplaceReviewerDialog', () => {
    const replace = () =>
        mountDialog(ReplaceReviewerDialog, {
            outgoing,
            candidates: [
                candidate(),
                candidate({
                    reviewerId: 22,
                    name: 'Dr. Andi',
                    blockedReason: 'Konflik kepentingan',
                }),
            ],
        })

    it('kandidat yang diblok disembunyikan', async () => {
        await replace()

        expect(text()).toContain('Dr. Rina Wijaya')
        expect(text()).not.toContain('Dr. Andi')
    })

    it('[REV-REASSIGN-04] ganti tanpa catatan mengirim note kosong', async () => {
        await replace()

        await click('Dr. Sari Lestari')
        await click('Ganti Reviewer')

        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.replace-reviewer/9,5')
        expect(form.incoming_reviewer_id).toBe(21)
        expect(form.note).toBe('')
    })

    it('[REV-REASSIGN-05] ganti mengirim catatan', async () => {
        await replace()

        await click('Dr. Sari Lestari')
        const note = document.querySelector<HTMLTextAreaElement>('#replace-note')
        if (note === null) {
            throw new Error('field catatan tidak ada')
        }
        note.value = 'Tidak responsif lebih dari 7 hari'
        note.dispatchEvent(new Event('input'))
        await flushPromises()
        await click('Ganti Reviewer')

        expect(post.mock.calls[0][0].note).toBe('Tidak responsif lebih dari 7 hari')
    })
})
