import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, page } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
    page: { flash: {} as { deadlineExtended?: number } },
}))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router,
        usePage: () => page,
        useForm: <T extends object>(data: T) =>
            reactive({
                ...data,
                processing: false,
                errors: {},
                post: vi.fn(),
                put: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
                defaults: vi.fn(),
            }),
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
    }
})
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import PeriodIndexPage from '@/Pages/FormEngine/PeriodIndexPage.vue'
import type { PeriodIndexPageProps, PeriodRowData } from '@/types/form-engine'

const periodRow = (overrides: Partial<PeriodRowData> = {}): PeriodRowData => ({
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
            phaseDetailCount: 0,
        },
    ],
    formPhaseIds: [2],
    submittedCount: 42,
    draftOwnerCount: 3,
    closable: true,
    deleteBlockers: [],
    ...overrides,
})

const props = (
    rows: PeriodRowData[],
    overrides: Partial<PeriodIndexPageProps> = {}
): PeriodIndexPageProps => ({
    periods: {
        data: rows,
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: rows.length,
        from: 1,
        to: rows.length,
        prev_page_url: null,
        next_page_url: null,
    },
    filters: { search: '', status: '', year: '' },
    statusOptions: [{ value: 'active', label: 'Dibuka' }],
    yearOptions: [{ value: '2026', label: '2026' }],
    labelOptions: ['Batas Submit'],
    phaseOptions: [{ value: '2', label: 'Proposal' }],
    can: { viewSubmissions: true },
    ...overrides,
})

const route = (name: string, params?: number | Record<string, number>) => {
    if (params === undefined) return `/${name}`
    if (typeof params === 'number') return `/${name}/${params}`
    return `/${name}?${new URLSearchParams(Object.entries(params).map(([k, v]) => [k, String(v)]))}`
}

const mountPage = (rows: PeriodRowData[], overrides: Partial<PeriodIndexPageProps> = {}) =>
    mount(PeriodIndexPage, {
        props: props(rows, overrides),
        global: { mocks: { route } },
        attachTo: document.body,
    })

beforeEach(() => {
    vi.stubGlobal('route', route)
    page.flash = {}
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('FormEngine/PeriodIndexPage', () => {
    it('menggambar judul, kolom, dan dua banner frame', () => {
        const text = mountPage([periodRow()]).text()

        expect(text).toContain('Periode Pengajuan')
        expect(text).toContain('Kelola jendela waktu penerimaan pengajuan proposal.')
        for (const column of ['Nama Periode', 'Tanggal Penting', 'Pengajuan Masuk', 'Status']) {
            expect(text).toContain(column)
        }
        expect(text).toContain('Periode ditutup otomatis saat seluruh tenggatnya terlewat.')
        expect(text).toContain('Memundurkan salah satu tanggal periode akan otomatis memberi tahu')
        expect(text).toContain('Batas Submit: 15 Feb 2026')
    })

    it('baris yang tidak bisa ditutup tidak menawarkan "Tutup"', () => {
        const buttons = (rows: PeriodRowData[]) =>
            mountPage(rows)
                .findAll('button')
                .map((b) => b.text())

        expect(buttons([periodRow()])).toContain('Tutup')
        expect(
            buttons([
                periodRow({ closable: false, status: { value: 'expired', label: 'Ditutup' } }),
            ])
        ).not.toContain('Tutup')
    })

    it('"Pengajuan Masuk" menaut ke Semua Pengajuan bertapis periode bila boleh dibuka', () => {
        const link = mountPage([periodRow()]).find('a[href="/admin.submissions.index?period=4"]')
        expect(link.exists()).toBe(true)
        expect(link.text()).toBe('42')

        const plain = mountPage([periodRow()], { can: { viewSubmissions: false } })
        expect(plain.find('a[href^="/admin.submissions.index"]').exists()).toBe(false)
        expect(plain.text()).toContain('42')
    })

    it('[SCH-RULE-13] banner tanpa peneliti saat flash 0', () => {
        page.flash = { deadlineExtended: 0 }
        expect(mountPage([periodRow()]).text()).toContain(
            'Tenggat diperbarui. Tidak ada peneliti yang perlu diberi tahu.'
        )
    })

    it('[SCH-RULE-11] banner menyebut jumlah yang diberi tahu', () => {
        page.flash = { deadlineExtended: 7 }
        const text = mountPage([periodRow()]).text()
        expect(text).toContain('Tenggat diperbarui. 7 peneliti yang masih punya draft diberi tahu.')
    })

    it('tanpa flash perpanjangan tidak ada banner tenggat', () => {
        expect(mountPage([periodRow()]).text()).not.toContain('Tenggat diperbarui')
    })

    it('"Buat Periode Baru" dan "Edit" membuka dialog periode', async () => {
        const wrapper = mountPage([periodRow()])
        const click = async (label: string) => {
            await wrapper
                .findAll('button')
                .find((b) => b.text().trim() === label)
                ?.trigger('click')
        }

        await click('Buat Periode Baru')
        expect(document.body.textContent).toContain('Simpan Periode')
        expect(document.body.textContent).not.toContain('Sunting Periode')
        wrapper.unmount()

        const second = mountPage([periodRow()])
        await second
            .findAll('button')
            .find((b) => b.text().trim() === 'Edit')
            ?.trigger('click')
        expect(document.body.textContent).toContain('Sunting Periode')
    })

    it('daftar kosong menampilkan state kosong', () => {
        expect(mountPage([]).text()).toContain('Belum ada periode pengajuan')
    })
})
