import { mount } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, page } = vi.hoisted(() => ({
    router: { post: vi.fn(), reload: vi.fn() },
    page: { props: { flash: { workloadWarnings: null as string[] | null } } },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: () => page,
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post: vi.fn(), reset: vi.fn() }),
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import ManageShowPage from '@/Pages/Submission/ManageShowPage.vue'
import type { ManageShowPageProps, ManagedReviewer } from '@/types/submission-manage'
import type { SubmissionStatusKey } from '@/types/submission-status'

const reviewer = (overrides: Partial<ManagedReviewer> = {}): ManagedReviewer => ({
    reviewerId: 5,
    name: 'Dr. Rina Wijaya',
    nidn: '0011223344',
    type: 'internal',
    replaced: false,
    evaluationLabel: 'Evaluasi berjalan',
    accountActive: true,
    ...overrides,
})

const props = (
    overrides: Partial<ManageShowPageProps> = {},
    status: SubmissionStatusKey = 'submitted'
) => {
    const base: ManageShowPageProps = {
        submission: {
            id: 9,
            title: 'Analisis Potensi Biochar di Kalimantan Timur',
            scheme: 'Penelitian DIPA 2026',
            period: 'DIPA 2026',
            trl: null,
            leadName: 'Dr. Budi Santoso',
            status: { key: status, label: status, variant: 'secondary' },
            submittedAt: '2026-01-12T08:00:00+08:00',
            isArchived: false,
            viewerRole: 'viewer',
            viewerRoleLabel: 'Tidak terlibat',
            editUrl: null,
        },
        answers: [],
        members: [{ name: 'Dr. Budi Santoso', roleLabel: 'Ketua' }],
        targetOutputs: [],
        targetHistory: [],
        budget: { items: [], total: 0, editable: false, lockReason: 'read_only' },
        history: [],
        decision: null,
        scores: { visible: true, reason: null, scores: [] },
        phaseAccess: { is_open: true, deadline: null, form_phase_detail_id: 3, override: null },
        printableDocuments: [],
        can: {
            view: true,
            update: false,
            submit: false,
            resubmit: false,
            withdraw: false,
            manageTargetOutput: false,
            discussReview: false,
        },
        organization: 'FTI — Teknik Mesin',
        reviewers: [],
        activeReviewerCount: 0,
        minimumReviewers: 2,
        needsReview: true,
        evaluationSheets: [],
        threads: [],
        ownershipCandidates: [],
        overrideTarget: { phaseLabel: 'Fase Proposal — Batas Submit', affectedReviewers: [] },
        operatorCan: {
            assignReviewers: true,
            replaceReviewer: true,
            grantOverride: true,
            transferOwnership: true,
            approve: false,
            reject: false,
            withdraw: false,
        },
        decisionReadiness: {
            ready: false,
            approvable: false,
            blockers: ['Belum ada reviewer.'],
            openThreadCount: 0,
            round: null,
        },
        outputs: null,
        outputAccess: null,
        outputPhaseAccess: null,
        outputOverrideTarget: null,
    }
    return { ...base, ...overrides }
}

const route = (name: string) => `/${name}`

const mountPage = (pageProps: ManageShowPageProps) =>
    mount(ManageShowPage, { props: pageProps, global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.post.mockClear()
    page.props.flash.workloadWarnings = null
})

describe('ManageShowPage', () => {
    it('[REV-ASSIGN-02] indikator 1 dari minimal 2', () => {
        const wrapper = mountPage(props({ reviewers: [reviewer()], activeReviewerCount: 1 }))

        expect(wrapper.text()).toContain('1 dari minimal 2')
    })

    it('[REV-OUTCOME-10] menunggu penugasan reviewer', () => {
        expect(mountPage(props()).text()).toContain('Pengajuan menunggu penugasan reviewer.')
    })

    it('menunggu penugasan tidak tampil setelah ada reviewer aktif', () => {
        const wrapper = mountPage(props({ reviewers: [reviewer()], activeReviewerCount: 1 }))

        expect(wrapper.text()).not.toContain('Pengajuan menunggu penugasan reviewer.')
    })

    it('[REV-REASSIGN-01] reviewer digantikan tampil berlabel tanpa tombol ganti', () => {
        const wrapper = mountPage(
            props({
                reviewers: [
                    reviewer(),
                    reviewer({ reviewerId: 6, name: 'Dr. Andi Mappa', replaced: true }),
                ],
                activeReviewerCount: 1,
            })
        )

        expect(wrapper.text()).toContain('Digantikan')
        const replaceButtons = wrapper.findAll('button').filter((b) => b.text() === 'Ganti')
        expect(replaceButtons).toHaveLength(1)
    })

    it('[SM-WITHDRAW-05] pengajuan ditarik menampilkan banner final tanpa aksi tersedia', () => {
        const wrapper = mountPage(
            props(
                {
                    needsReview: false,
                    operatorCan: {
                        assignReviewers: false,
                        replaceReviewer: false,
                        grantOverride: false,
                        transferOwnership: false,
                        approve: false,
                        reject: false,
                        withdraw: false,
                    },
                },
                'withdrawn'
            )
        )

        expect(wrapper.text()).toContain('Penarikan bersifat final.')
        const actionLabels = [
            'Tugaskan Reviewer',
            'Perpanjang Tenggat',
            'Alihkan Kepemilikan',
            'Tolak Manual',
            'Tarik Pengajuan',
        ]
        for (const label of actionLabels) {
            const button = wrapper.findAll('button').find((b) => b.text().includes(label))
            expect(button?.attributes('disabled')).toBeDefined()
        }
    })

    it('[REV-OUTCOME-12] item Setujui tidak ada selama belum siap diputuskan', () => {
        const labels = mountPage(props())
            .findAll('button')
            .map((b) => b.text())

        expect(labels.some((label) => label.includes('Setujui'))).toBe(false)
    })

    it('[REV-OUTCOME-03] Setujui nonaktif dengan penahan pertama selama catatan terbuka', () => {
        const wrapper = mountPage(
            props(
                {
                    operatorCan: { ...props().operatorCan, approve: true, reject: true },
                    decisionReadiness: {
                        ready: true,
                        approvable: false,
                        blockers: ['1 catatan reviewer masih terbuka.'],
                        openThreadCount: 1,
                        round: { kind: 'assessment', number: 1, finished: 2, total: 2 },
                    },
                },
                'under_review'
            )
        )

        const approve = wrapper.findAll('button').find((b) => b.text().includes('Setujui'))
        expect(approve?.attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('1 catatan reviewer masih terbuka.')
    })

    it('[REV-OUTCOME-11] Setujui tersedia bila siap tanpa catatan terbuka', () => {
        const wrapper = mountPage(
            props(
                {
                    operatorCan: { ...props().operatorCan, approve: true, reject: true },
                    decisionReadiness: {
                        ready: true,
                        approvable: true,
                        blockers: [],
                        openThreadCount: 0,
                        round: { kind: 'assessment', number: 1, finished: 2, total: 2 },
                    },
                },
                'under_review'
            )
        )

        const approve = wrapper.findAll('button').find((b) => b.text().includes('Setujui'))
        expect(approve).toBeDefined()
        expect(approve?.attributes('disabled')).toBeUndefined()
    })

    it('[BUD-WHO-04] anggaran tampil baca-saja', async () => {
        const wrapper = mountPage(props())
        const budgetTab = wrapper.findAll('button[role="tab"]').find((t) => t.text() === 'Anggaran')

        await budgetTab?.trigger('mousedown', { button: 0 })

        expect(wrapper.text()).toContain('Hanya baca')
    })

    it('[RPT-PRINT-01] operator mencetak dokumen dari detail', async () => {
        const wrapper = mountPage(
            props({ printableDocuments: [{ value: 'proposal', label: 'Proposal' }] })
        )

        await wrapper
            .findAll('button')
            .find((b) => b.text().includes('Cetak Proposal'))
            ?.trigger('click')

        expect(router.post).toHaveBeenCalledWith('/submissions.print', {}, expect.anything())
    })

    it('subjudul memuat skema, ketua, dan organisasi', () => {
        expect(mountPage(props()).text()).toContain(
            'Penelitian DIPA 2026 · Ketua: Dr. Budi Santoso · FTI — Teknik Mesin'
        )
    })

    it('[REV-ASSIGN-06] peringatan beban dari flash ditampilkan', () => {
        const warning =
            'Reviewer ini sedang memegang 9 penugasan aktif, melebihi batas 8 yang ditetapkan skema. Penugasan tetap dapat dilanjutkan.'
        page.props.flash.workloadWarnings = [warning]

        expect(mountPage(props()).text()).toContain(warning)
    })

    it('[RO-OUTPUT-20] tab Luaran hanya tampil bila backend mengirim luaran (W144)', () => {
        const output = { id: 4, typeName: 'Buku', headline: 'Panduan', meta: null, fileCount: 0 }

        expect(mountPage(props()).text()).not.toContain('Luaran (')
        expect(
            mountPage(
                props(
                    {
                        outputs: [output],
                        outputAccess: {
                            canWrite: true,
                            blockedReason: null,
                            deadline: null,
                            overrideUntil: null,
                        },
                    },
                    'approved'
                )
            ).text()
        ).toContain('Luaran (1)')
    })
})
