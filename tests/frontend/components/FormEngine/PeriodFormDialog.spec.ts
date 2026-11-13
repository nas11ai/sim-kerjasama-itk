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

import OptionSelect from '@/Components/app/OptionSelect.vue'
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
            type: 'submit',
            cycle: null,
            label: 'Batas Pengiriman',
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

// Baris ke-n punya dua OptionSelect: jenis (2n) dan siklus (2n + 1).
const choose = async (row: number, field: 'type' | 'cycle', value: string) => {
    const select = wrapper.findAllComponents(OptionSelect)[row * 2 + (field === 'type' ? 0 : 1)]
    if (select === undefined) throw new Error(`select ${field} baris ${row} tidak ada`)
    select.vm.$emit('update:modelValue', value)
    await flushPromises()
}

const open = async (row: PeriodRowData | null) => {
    wrapper = mount(PeriodFormDialog, {
        props: {
            open: true,
            period: row,
            typeOptions: [
                { value: 'submit', label: 'Batas Pengiriman' },
                { value: 'evaluation', label: 'Batas Evaluasi' },
                { value: 'revision', label: 'Batas Revisi' },
            ],
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

        const [type, cycle] = wrapper.findAllComponents(OptionSelect)
        expect(type?.props('disabled')).toBe(true)
        expect(cycle?.props('disabled')).toBe(true)
        expect(
            document.querySelector<HTMLButtonElement>('[aria-label="Buang tenggat 1"]')?.disabled
        ).toBe(true)
        expect(text()).toContain('Dipakai 1 detail tahap, tidak bisa dibuang.')
    })

    it('[SCH-RULE-11] menyebut jumlah pemilik draft saat menyunting', async () => {
        await open(period())

        expect(text()).toContain(
            'Memundurkan Batas Pengiriman atau Batas Revisi akan memberi tahu 3 peneliti yang masih punya draf di periode ini.'
        )
    })

    it('periode ditutup Operator: memundurkan tenggat tidak membukanya kembali', async () => {
        await open(period({ status: { value: 'force_closed', label: 'Ditutup Operator' } }))

        expect(text()).toContain('Periode ini ditutup Operator.')
        expect(text()).not.toContain('akan memberi tahu')
    })

    it('[FE-WINDOW-12] mengirim store saat membuat, jenis, siklus, dan tanggal tiap baris', async () => {
        await open(null)
        expect(text()).toContain('Buat Periode Baru')

        await fill('#period-name', 'Pengabdian 2027')
        await choose(0, 'type', 'evaluation')
        await choose(0, 'cycle', '1')
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
            { type: 'evaluation', cycle: 1, date: '2027-03-01T23:59' },
        ])
        expect(form.form_phase_ids).toEqual([5])
    })

    it('mengirim update saat menyunting dan bisa menambah tenggat', async () => {
        await open(period({ dates: [{ ...period().dates[0], phaseDetailCount: 0 }] }))
        expect(text()).toContain('Sunting Periode')

        button('Tambah Tenggat').click()
        await flushPromises()
        await choose(1, 'type', 'revision')
        await fill('#period-date-at-1', '2026-03-15T23:59')
        button('Simpan Periode').click()
        await flushPromises()

        const [form, url] = put.mock.calls[0]
        expect(url).toBe('/admin.submission-periods.update/4')
        expect(form.submission_dates).toEqual([
            { type: 'submit', cycle: null, date: '2026-02-15T23:59' },
            { type: 'revision', cycle: null, date: '2026-03-15T23:59' },
        ])
        expect(form.form_phase_ids).toEqual([2])
    })

    it('baris baru dimulai sebagai Batas Submit pengajuan utama', async () => {
        await open(null)

        const [type, cycle] = wrapper.findAllComponents(OptionSelect)
        expect(type?.props('modelValue')).toBe('submit')
        expect(cycle?.props('modelValue')).toBe('main')
        expect(cycle?.props('options')).toContainEqual({ value: '2', label: 'Monev II' })
    })

    it('menjelaskan bahwa periode menerima pengajuan baru sampai Batas Submit (K8d)', async () => {
        await open(null)

        expect(text()).toContain('menerima pengajuan baru sampai Batas Pengiriman')
    })
})
