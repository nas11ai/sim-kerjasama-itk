import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type Visit = { onSuccess?: () => void; onError?: () => void; onFinish?: () => void }
type FakeForm = { errors: Record<string, string>; [key: string]: unknown }

const { post } = vi.hoisted(() => ({
    post: vi.fn<(form: FakeForm, url: string, options: Visit) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
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

import OptionSelect from '@/Components/app/OptionSelect.vue'
import ApproveDialog from '@/Components/Submission/Manage/ApproveDialog.vue'
import RejectDialog from '@/Components/Submission/Manage/RejectDialog.vue'
import TransferOwnershipDialog from '@/Components/Submission/Manage/TransferOwnershipDialog.vue'
import WithdrawDialog from '@/Components/Submission/Manage/WithdrawDialog.vue'
import type { SubmissionDetail } from '@/types/submission-page'

const route = (name: string, params: unknown) => `/${name}/${String(params)}`

const submission: SubmissionDetail = {
    id: 9,
    title: 'Analisis Potensi Biochar',
    scheme: 'Penelitian DIPA 2026',
    period: 'DIPA 2026',
    trl: null,
    leadName: 'Dr. Budi Santoso',
    status: { key: 'under_review', label: 'Direview', variant: 'secondary' },
    submittedAt: null,
    isArchived: false,
    viewerRole: 'viewer',
    viewerRoleLabel: 'Tidak terlibat',
    editUrl: null,
}

// Konten dialog dirender lewat portal ke body, jadi dicari dari document.
const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) {
        throw new Error(`tombol "${label}" tidak ada`)
    }
    return found
}

const type = async (selector: string, value: string) => {
    const field = document.querySelector<HTMLTextAreaElement>(selector)
    if (field === null) {
        throw new Error(`field ${selector} tidak ada`)
    }
    field.value = value
    field.dispatchEvent(new Event('input'))
    await flushPromises()
}

const click = async (label: string) => {
    button(label).click()
    await flushPromises()
}

let wrapper: VueWrapper

const open = async (component: object, props: Record<string, unknown>) => {
    wrapper = mount(component, {
        props: { open: true, submission, ...props },
        attachTo: document.body,
        global: { mocks: { route } },
    })
    await flushPromises()
}

const failWith = (field: string, message: string) =>
    post.mockImplementation((form, _url, options) => {
        form.errors = { [field]: message }
        options.onError?.()
        options.onFinish?.()
    })

beforeEach(() => {
    vi.stubGlobal('route', route)
    post.mockReset()
})

afterEach(() => wrapper.unmount())

describe('dialog keputusan Operator', () => {
    it('[REV-OUTCOME-11] Setujui mengirim ke admin.submissions.approve setelah konfirmasi', async () => {
        await open(ApproveDialog, {
            readiness: {
                ready: true,
                approvable: true,
                blockers: [],
                openThreadCount: 0,
                round: { kind: 'assessment', number: 1, finished: 2, total: 2 },
            },
        })

        expect(document.body.textContent).toContain('Putaran 1: 2 dari 2 reviewer selesai')
        await click('Setujui Pengajuan')

        const [, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.approve/9')
    })

    it('[REV-OUTCOME-03] penolakan Setujui dari backend tampil sebagai banner', async () => {
        failWith('approve', '1 catatan reviewer masih terbuka.')
        await open(ApproveDialog, {
            readiness: {
                ready: true,
                approvable: true,
                blockers: [],
                openThreadCount: 0,
                round: null,
            },
        })

        await click('Setujui Pengajuan')

        expect(document.body.textContent).toContain('1 catatan reviewer masih terbuka.')
    })

    it('[REV-OUTCOME-07] tolak mengirim alasan setelah konfirmasi kedua', async () => {
        await open(RejectDialog, { activeReviewerCount: 2 })

        expect(document.body.textContent).toContain('Reviewer Aktif')
        await type('#reject-reason', 'Plagiarisme terbukti pada bab metodologi.')
        await click('Tolak Pengajuan')
        expect(post).not.toHaveBeenCalled()
        expect(document.body.textContent).toContain(
            'Pengajuan yang ditolak tidak dapat diaktifkan kembali.'
        )

        await click('Tolak')

        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.reject/9')
        expect(form.reason).toBe('Plagiarisme terbukti pada bab metodologi.')
    })

    it('[SM-WITHDRAW-01] tarik mengirim alasan setelah konfirmasi kedua', async () => {
        await open(WithdrawDialog, { outputCount: 2 })

        // W144 — luaran yang sudah terdaftar tetap tersimpan setelah penarikan (BR-RO-06).
        expect(document.body.textContent).toContain('Luaran Terdaftar')
        await type('#withdraw-reason', 'Duplikasi pendanaan')
        await click('Tarik Pengajuan')
        expect(post).not.toHaveBeenCalled()

        await click('Tarik')

        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.withdraw/9')
        expect(form.reason).toBe('Duplikasi pendanaan')
    })

    it('[SM-WITHDRAW-06] alasan penarikan kosong ditampilkan sebagai galat field', async () => {
        failWith('reason', 'Alasan wajib diisi.')
        await open(WithdrawDialog, { outputCount: 2 })

        await click('Tarik Pengajuan')
        await click('Tarik')

        expect(document.body.textContent).toContain('Alasan wajib diisi.')
        expect(wrapper.emitted('update:open')).toBeUndefined()
    })

    it('[SM-OWNER-01] alihkan mengirim penerima dan alasan', async () => {
        await open(TransferOwnershipDialog, {
            candidates: [{ userId: 12, name: 'Dr. Rina Wulandari', organization: 'FTI' }],
        })

        expect(document.body.textContent).toContain('Dr. Rina Wulandari')
        wrapper.findComponent(OptionSelect).vm.$emit('update:modelValue', '12')
        await type('#transfer-reason', 'Ketua pindah institusi')
        await click('Alihkan Kepemilikan')

        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submissions.transfer-ownership/9')
        expect(form.successor_id).toBe('12')
        expect(form.reason).toBe('Ketua pindah institusi')
    })

    it('[SM-OWNER-02] galat penerima tampil di dialog yang tetap terbuka', async () => {
        const message =
            'Kepemilikan hanya dapat dipindahkan ke co_investigator yang masih aktif pada pengajuan ini.'
        failWith('successor_id', message)
        await open(TransferOwnershipDialog, {
            candidates: [{ userId: 12, name: 'Dr. Rina Wulandari', organization: null }],
        })

        await type('#transfer-reason', 'Ketua pindah institusi')
        await click('Alihkan Kepemilikan')

        expect(document.body.textContent).toContain(message)
        expect(wrapper.emitted('update:open')).toBeUndefined()
        expect(document.querySelector<HTMLTextAreaElement>('#transfer-reason')?.value).toBe(
            'Ketua pindah institusi'
        )
    })

    it('galat status dari Action tampil di dialog tolak', async () => {
        failWith('status', 'Penolakan manual hanya untuk pengajuan yang sedang direview.')
        await open(RejectDialog, { activeReviewerCount: 0 })

        await click('Tolak Pengajuan')
        await click('Tolak')

        expect(document.body.textContent).toContain(
            'Penolakan manual hanya untuk pengajuan yang sedang direview.'
        )
    })
})
