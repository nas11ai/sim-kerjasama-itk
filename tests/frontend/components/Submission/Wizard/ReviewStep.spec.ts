import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, page } = vi.hoisted(() => ({
    post: vi.fn(),
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({ router: { post }, usePage: () => page }))

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import ReviewStep from '@/Components/Submission/Wizard/ReviewStep.vue'
import type { SubmissionAbilities } from '@/types/submission-page'
import type { WizardReview, WizardSubmission } from '@/types/submission-wizard'

const submission: WizardSubmission = {
    id: 7,
    title: 'Formulir Proposal',
    status: { key: 'draft', label: 'Draf', variant: 'secondary' },
    period: 'DIPA 2026',
    form: 'Formulir Proposal',
    lastUpdatedAt: '2026-10-31T01:00:00.000000Z',
}

const review: WizardReview = {
    scheme: 'Penelitian DIPA',
    trl: 'TKT 4 · Validasi Lab',
    memberCount: 3,
    targetOutputCount: 2,
    budgetTotal: 45_000_000,
    maxBudget: 50_000_000,
    fileNames: ['proposal.pdf'],
    missingFields: [],
    accountBlock: null,
}

const can: SubmissionAbilities = {
    view: true,
    update: true,
    submit: true,
    resubmit: false,
    withdraw: false,
    manageTargetOutput: false,
    discussReview: false,
}

const mountReview = (r: Partial<WizardReview> = {}, c: Partial<SubmissionAbilities> = {}) =>
    mount(ReviewStep, { props: { submission, review: { ...review, ...r }, can: { ...can, ...c } } })

const action = (wrapper: ReturnType<typeof mountReview>, name: string) =>
    wrapper.find(`button[data-action="${name}"]`)

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: unknown) => `/${name}/${String(params)}`)
    post.mockReset()
    page.props.errors = {}
})

describe('ReviewStep', () => {
    it('[SM-WIZARD-06] mendaftar field wajib yang kosong dengan tautan langkah', async () => {
        const wrapper = mountReview({
            missingFields: [
                {
                    fieldId: 1,
                    label: 'Judul',
                    message: "Isian 'Judul' wajib diisi sebelum dikirim.",
                    step: 2,
                },
                {
                    fieldId: 9,
                    label: 'Proposal PDF',
                    message: "Isian 'Proposal PDF' wajib diisi sebelum dikirim.",
                    step: 6,
                },
            ],
        })

        expect(wrapper.text()).toContain('2 isian wajib belum terisi. Perbaiki sebelum mengirim.')
        expect(wrapper.text()).toContain(
            "Proposal PDF · Isian 'Proposal PDF' wajib diisi sebelum dikirim."
        )

        await wrapper.find('button[data-goto="6"]').trigger('click')

        expect(wrapper.emitted('goto')).toEqual([[6]])
    })

    it('[IAM-VERIFY-02] akun pending: tombol Submit nonaktif dengan alasan', () => {
        const reason =
            'Akun Anda belum terverifikasi. Pengajuan dapat disiapkan, tapi belum dapat dikirim.'
        const wrapper = mountReview({ accountBlock: reason })

        expect(wrapper.text()).toContain(reason)
        expect(action(wrapper, 'submit').attributes('disabled')).toBeDefined()
    })

    it('[SM-SUBMIT-13] bukan ketua: tombol Submit tidak dirender', () => {
        const wrapper = mountReview({}, { submit: false, resubmit: false })

        expect(action(wrapper, 'submit').exists()).toBe(false)
        expect(action(wrapper, 'resubmit').exists()).toBe(false)
        expect(wrapper.text()).toContain('Hanya ketua pengaju yang dapat mengirim pengajuan ini.')
    })

    it('[SM-WIZARD-08] submit nonaktif selama field wajib kosong', () => {
        const wrapper = mountReview({
            missingFields: [{ fieldId: 1, label: 'Judul', message: 'wajib', step: 2 }],
        })

        expect(action(wrapper, 'submit').attributes('disabled')).toBeDefined()
    })

    it('submit lewat ConfirmDialog memanggil user.submissions.submit', async () => {
        const wrapper = mountReview()

        await action(wrapper, 'submit').trigger('click')
        wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')

        expect(post).toHaveBeenCalledWith('/user.submissions.submit/7', {}, expect.any(Object))
    })

    it('revisi menawarkan Kirim Ulang Revisi', async () => {
        const wrapper = mountReview({}, { submit: true, resubmit: true })

        expect(action(wrapper, 'submit').exists()).toBe(false)
        await action(wrapper, 'resubmit').trigger('click')
        wrapper.findComponent(ConfirmDialog).vm.$emit('confirm')

        expect(post).toHaveBeenCalledWith('/user.submissions.resubmit/7', {}, expect.any(Object))
    })

    it('menampilkan error penolakan sebagai teks', () => {
        page.props.errors = {
            scheme_id:
                'Anda sudah punya pengajuan aktif pada skema ini: #3 (<a href="x">tautan</a>).',
        }

        const wrapper = mountReview()

        expect(wrapper.text()).toContain('#3 (<a href="x">tautan</a>)')
        expect(wrapper.find('a[href="x"]').exists()).toBe(false)
    })
})
