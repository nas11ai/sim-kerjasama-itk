import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

vi.mock('@inertiajs/vue3', () => ({
    router: { post },
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import SubmissionActions from '@/Components/Submission/SubmissionActions.vue'
import type { SubmissionAbilities, SubmissionDetail } from '@/types/submission-page'

const submission: SubmissionDetail = {
    id: 7,
    title: 'Analisis Potensi Biochar',
    scheme: 'Penelitian DIPA',
    period: 'DIPA 2026',
    trl: null,
    leadName: 'Dr. Rina',
    status: { key: 'draft', label: 'Draft', variant: 'secondary' },
    submittedAt: null,
    isArchived: false,
    viewerRole: 'lead_researcher',
    viewerRoleLabel: 'Ketua',
    editUrl: '/user/submission-period/1/form-phase/2',
}

const can: SubmissionAbilities = {
    view: true,
    update: true,
    submit: true,
    resubmit: false,
    withdraw: false,
    manageTargetOutput: false,
}

const mountActions = (
    overrides: { submission?: Partial<SubmissionDetail>; can?: Partial<SubmissionAbilities> } = {},
    printableDocuments: { value: string; label: string }[] = []
) =>
    mount(SubmissionActions, {
        props: {
            submission: { ...submission, ...overrides.submission },
            can: { ...can, ...overrides.can },
            printableDocuments,
        },
    })

beforeEach(() => {
    vi.stubGlobal('route', (name: string, params: unknown[]) => `/${name}/${params.join('/')}`)
    post.mockReset()
})

describe('SubmissionActions', () => {
    it('menautkan Edit Pengajuan ke editUrl saat can.update', () => {
        const link = mountActions().find('a')

        expect(link.text()).toContain('Edit Pengajuan')
        expect(link.attributes('href')).toBe('/user/submission-period/1/form-phase/2')
    })

    it('menamai aksi Mulai Revisi saat can.resubmit', () => {
        const wrapper = mountActions({ can: { resubmit: true } })

        expect(wrapper.find('a').text()).toContain('Mulai Revisi')
        expect(wrapper.text()).toContain('Tersedia saat status Perlu Revisi.')
    })

    it('[SM-ARCHIVE-01] draft terarsip: edit nonaktif dengan alasan arsip', () => {
        const wrapper = mountActions({ submission: { isArchived: true }, can: { update: false } })

        expect(wrapper.find('a').exists()).toBe(false)
        expect(wrapper.find('button').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain(
            'Draft dari periode yang sudah ditutup — tidak dapat diedit lagi.'
        )
    })

    it('[SM-SUBMIT-10] [SM-SUBMIT-11] tidak pernah menawarkan Submit', () => {
        const wrapper = mountActions({ can: { submit: true } })

        expect(wrapper.text()).not.toMatch(/Submit/)
    })

    it('[SM-WITHDRAW-02] peneliti diarahkan ke Operator untuk penarikan', () => {
        expect(mountActions().text()).toContain('Untuk menarik pengajuan, hubungi Operator LPPM.')
    })

    it('cetak memanggil router.post ke submissions.print', async () => {
        const wrapper = mountActions({}, [
            { value: 'acceptance_letter', label: 'Surat Keterangan' },
        ])

        const print = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Cetak Surat Keterangan'))
        await print?.trigger('click')

        expect(post).toHaveBeenCalledWith(
            '/submissions.print/7/acceptance_letter',
            {},
            { preserveScroll: true }
        )
    })
})
