import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

type FakeForm = { errors: Record<string, string>; [key: string]: unknown }

const { post, put } = vi.hoisted(() => ({
    post: vi.fn<(form: FakeForm, url: string) => void>(),
    put: vi.fn<(form: FakeForm, url: string) => void>(),
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) => {
            const form = reactive({
                ...data,
                errors: {},
                processing: false,
                clearErrors: vi.fn(),
                post: (url: string) => post(form, url),
                put: (url: string) => put(form, url),
            })
            return form
        },
    }
})

import PeriodFormDialog from '@/Components/FormEngine/PeriodFormDialog.vue'
import type { PeriodRowData } from '@/types/form-engine'

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const period = (overrides: Partial<PeriodRowData> = {}): PeriodRowData => ({
    id: 4,
    name: 'Penelitian DIPA 2026',
    status: { value: 'active', label: 'Dibuka' },
    dates: [
        {
            id: 11,
            label: 'Batas Submit',
            at: '2026-02-15T23:59:00+08:00',
            inputValue: '2026-02-15T23:59',
            passed: false,
            phaseDetailCount: 1,
        },
    ],
    formPhaseIds: [2],
    submittedCount: 42,
    draftOwnerCount: 3,
    closable: true,
    deleteBlockers: [],
    ...overrides,
})

const text = () => document.body.textContent ?? ''

const button = (label: string) => {
    const found = Array.from(document.querySelectorAll('button')).find(
        (b) => b.textContent?.trim() === label
    )
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

const fill = async (selector: string, value: string) => {
    const field = document.querySelector<HTMLInputElement>(selector)
    if (field === null) throw new Error(`field ${selector} tidak ada`)
    field.value = value
    field.dispatchEvent(new Event('input'))
    await flushPromises()
}

let wrapper: VueWrapper

const open = async (row: PeriodRowData | null) => {
    wrapper = mount(PeriodFormDialog, {
        props: {
            open: true,
            period: row,
            labelOptions: ['Batas Submit', 'Batas Evaluasi'],
            phaseOptions: [
                { value: '2', label: 'Proposal' },
                { value: '5', label: 'Monev' },
            ],
        },
        attachTo: document.body,
        global: { mocks: { route } },
    })
    await flushPromises()
}

beforeEach(() => vi.stubGlobal('route', route))

afterEach(() => {
    wrapper.unmount()
    post.mockClear()
    put.mockClear()
})

describe('PeriodFormDialog', () => {
    it('[FE-DELETE-11] baris tanggal yang dipakai detail tahap terkunci', async () => {
        await open(period())

        const label = document.querySelector<HTMLInputElement>('#period-date-label-0')
        expect(label?.readOnly).toBe(true)
        expect(
            document.querySelector<HTMLButtonElement>('[aria-label="Buang tenggat 1"]')?.disabled
        ).toBe(true)
        expect(text()).toContain('Dipakai 1 detail tahap — tidak bisa dibuang.')
    })

    it('[SCH-RULE-11] menyebut jumlah pemilik draft saat menyunting', async () => {
        await open(period())

        expect(text()).toContain(
            'Memundurkan tenggat akan memberi tahu 3 peneliti yang masih punya draft di periode ini.'
        )
    })

    it('periode ditutup Operator: memundurkan tenggat tidak membukanya kembali', async () => {
        await open(period({ status: { value: 'force_closed', label: 'Ditutup Operator' } }))

        expect(text()).toContain('Periode ini ditutup Operator.')
        expect(text()).not.toContain('Memundurkan tenggat akan memberi tahu')
    })

    it('mengirim store saat membuat, hanya label dan tanggal tiap baris', async () => {
        await open(null)
        expect(text()).toContain('Buat Periode Baru')

        await fill('#period-name', 'Pengabdian 2027')
        await fill('#period-date-label-0', 'Batas Evaluasi')
        await fill('#period-date-at-0', '2027-03-01T23:59')
        document.querySelector<HTMLButtonElement>('#period-phase-5')?.click()
        await flushPromises()
        button('Simpan Periode').click()
        await flushPromises()

        expect(post).toHaveBeenCalledOnce()
        const [form, url] = post.mock.calls[0]
        expect(url).toBe('/admin.submission-periods.store')
        expect(form.name).toBe('Pengabdian 2027')
        expect(form.submission_dates).toEqual([
            { label: 'Batas Evaluasi', date: '2027-03-01T23:59' },
        ])
        expect(form.form_phase_ids).toEqual([5])
    })

    it('mengirim update saat menyunting dan bisa menambah tenggat', async () => {
        await open(period({ dates: [{ ...period().dates[0], phaseDetailCount: 0 }] }))
        expect(text()).toContain('Sunting Periode')

        button('Tambah Tenggat').click()
        await flushPromises()
        await fill('#period-date-label-1', 'Batas Revisi')
        await fill('#period-date-at-1', '2026-03-15T23:59')
        button('Simpan Periode').click()
        await flushPromises()

        const [form, url] = put.mock.calls[0]
        expect(url).toBe('/admin.submission-periods.update/4')
        expect(form.submission_dates).toEqual([
            { label: 'Batas Submit', date: '2026-02-15T23:59' },
            { label: 'Batas Revisi', date: '2026-03-15T23:59' },
        ])
        expect(form.form_phase_ids).toEqual([2])
    })
})
