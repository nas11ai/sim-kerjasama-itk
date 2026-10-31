import { mount } from '@vue/test-utils'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({ router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() } }))

import TargetOutputsSection from '@/Components/Submission/TargetOutputsSection.vue'
import TargetOutputEditor from '@/Components/Submission/Wizard/TargetOutputEditor.vue'
import type { SubmissionDetail, TargetOutputChange } from '@/types/submission-page'
import type { SubmissionStatusKey } from '@/types/submission-status'

const submission: SubmissionDetail = {
    id: 7,
    title: 'Analisis Potensi Biochar',
    scheme: 'Penelitian DIPA',
    period: 'DIPA 2026',
    trl: null,
    leadName: 'Dr. Rina',
    status: { key: 'under_review', label: 'Direview', variant: 'secondary' },
    submittedAt: null,
    isArchived: false,
    viewerRole: 'lead_researcher',
    viewerRoleLabel: 'Ketua',
    editUrl: null,
}

const change: TargetOutputChange = {
    description: 'Target luaran diubah',
    actor: 'Dr. Rina',
    at: '2026-10-01T08:00:00+08:00',
    from: 'Publikasi',
    to: 'HKI',
}

const mountSection = (
    options: {
        canManage?: boolean
        status?: SubmissionStatusKey
        viewerRole?: SubmissionDetail['viewerRole']
        history?: TargetOutputChange[]
    } = {}
) =>
    mount(TargetOutputsSection, {
        props: {
            submission: {
                ...submission,
                viewerRole: options.viewerRole ?? 'lead_researcher',
                status: { ...submission.status, key: options.status ?? 'under_review' },
            },
            targetOutputs: [{ id: 3, type: 'Publikasi', description: null }],
            types: [{ value: 'publikasi', label: 'Publikasi' }],
            history: options.history ?? [change],
            canManage: options.canManage ?? false,
        },
    })

beforeEach(() => {
    vi.stubGlobal('route', (name: string) => `/${name}`)
})

describe('TargetOutputsSection', () => {
    it('[SM-TARGET-06] ketua pengaju menyunting target lewat editor', () => {
        const wrapper = mountSection({ canManage: true, status: 'approved' })

        expect(wrapper.findComponent(TargetOutputEditor).exists()).toBe(true)
        expect(wrapper.text()).toContain('masih dapat diubah meski pengajuan sudah disetujui')
    })

    it('[SM-TARGET-08] co-investigator hanya membaca, dengan banner ketua pengaju', () => {
        const wrapper = mountSection({ viewerRole: 'co_investigator' })

        expect(wrapper.findComponent(TargetOutputEditor).exists()).toBe(false)
        expect(wrapper.text()).toContain('Hanya ketua pengaju yang dapat mengubah target luaran.')
        expect(wrapper.text()).toContain('Publikasi')
    })

    it('[SM-TARGET-12] target terkunci saat pengajuan ditolak', () => {
        const wrapper = mountSection({ status: 'rejected' })

        expect(wrapper.findComponent(TargetOutputEditor).exists()).toBe(false)
        expect(wrapper.text()).toContain(
            'Target luaran terkunci karena pengajuan ditolak atau ditarik.'
        )
    })

    it('[SM-TARGET-09] jejak perubahan menampilkan nilai lama dan baru', () => {
        const text = mountSection().text()

        expect(text).toContain('Jejak Perubahan Target')
        expect(text).toContain('Target luaran diubah')
        expect(text).toContain('Publikasi → HKI')
        expect(text).toContain('Dr. Rina')
    })

    it('jejak kosong menampilkan keadaan kosong', () => {
        expect(mountSection({ history: [] }).text()).toContain('Belum ada perubahan target')
    })
})
