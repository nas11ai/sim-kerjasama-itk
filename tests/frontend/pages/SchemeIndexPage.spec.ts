import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
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

import { formatRupiah } from '@/lib/format'
import SchemeIndexPage from '@/Pages/Scheme/IndexPage.vue'
import type { SchemeIndexPageProps, SchemeRowData } from '@/types/scheme'

const schemeRow = (overrides: Partial<SchemeRowData> = {}): SchemeRowData => ({
    id: 4,
    name: 'Penelitian DIPA',
    code: 'PEN-DIPA',
    submissionType: 'Penelitian',
    schemeType: 'Kompetitif',
    maxBudget: 50_000_000,
    maxMembers: 5,
    durationMonths: 12,
    activeSubmissionCount: 0,
    isActive: true,
    ...overrides,
})

const props = (
    rows: SchemeRowData[],
    overrides: Partial<SchemeIndexPageProps> = {}
): SchemeIndexPageProps => ({
    schemes: {
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
    filters: { search: '', type: '', status: '' },
    typeOptions: [{ value: '1', label: 'Penelitian' }],
    statusOptions: [
        { value: 'active', label: 'Aktif' },
        { value: 'inactive', label: 'Nonaktif' },
    ],
    ...overrides,
})

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const mountPage = (rows: SchemeRowData[], overrides: Partial<SchemeIndexPageProps> = {}) =>
    mount(SchemeIndexPage, {
        props: props(rows, overrides),
        global: { mocks: { route } },
    })

const button = (wrapper: VueWrapper, label: string) =>
    wrapper.findAll('button').find((b) => b.text().trim() === label)

const bodyButton = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('Scheme/IndexPage', () => {
    it('menggambar judul, subjudul, kolom, isi baris, dan catatan', () => {
        const text = mountPage([schemeRow()]).text()

        expect(text).toContain('Skema Penelitian')
        expect(text).toContain('Kelola skema pendanaan beserta aturan dan batasannya.')
        for (const column of [
            'Nama',
            'Kode',
            'Tipe',
            'Anggaran Maks.',
            'Anggota Maks.',
            'Durasi',
            'Pengajuan Aktif',
            'Status',
        ]) {
            expect(text).toContain(column)
        }
        expect(text).toContain('PEN-DIPA')
        expect(text).toContain(formatRupiah(50_000_000))
        expect(text).toContain('12 bln')
        expect(text).toContain(
            'Skema yang masih dipakai pengajuan aktif tidak dapat dinonaktifkan. Menonaktifkan skema memerlukan konfirmasi.'
        )
    })

    it('"Buat Skema Baru" dan "Edit" menaut ke halaman form skema', () => {
        const wrapper = mountPage([schemeRow()])

        expect(wrapper.find('a[href="/admin.schemes.create"]').text()).toContain('Buat Skema Baru')
        expect(wrapper.find('a[href="/admin.schemes.edit/4"]').text()).toContain('Ubah')
    })

    it('[SCH-RULE-04] nonaktifkan dimatikan dan menyebut jumlah pengajuan aktif', () => {
        const wrapper = mountPage([schemeRow({ activeSubmissionCount: 3 })])

        expect(button(wrapper, 'Nonaktifkan')?.attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain(
            'Masih ada 3 pengajuan aktif yang memakai skema ini. Selesaikan atau tarik pengajuan itu lebih dulu.'
        )
    })

    it('[SCH-RULE-06] skema tanpa pengajuan aktif bisa dinonaktifkan lewat konfirmasi', async () => {
        const wrapper = mountPage([schemeRow()])

        await button(wrapper, 'Nonaktifkan')?.trigger('click')
        await flushPromises()
        expect(router.post).not.toHaveBeenCalled()
        expect(document.body.textContent).toContain('Nonaktifkan skema?')
        expect(document.body.textContent).toContain(
            'Penelitian DIPA tidak lagi ditawarkan pada pengajuan baru. Pengajuan yang sudah memakainya tidak terpengaruh.'
        )

        bodyButton('Nonaktifkan Skema')?.click()
        await flushPromises()
        expect(router.post).toHaveBeenCalledWith(
            '/admin.schemes.deactivate/4',
            {},
            expect.anything()
        )
    })

    it('baris nonaktif menawarkan Aktifkan dan langsung mengirimnya', async () => {
        const wrapper = mountPage([schemeRow({ isActive: false })])

        expect(button(wrapper, 'Nonaktifkan')).toBeUndefined()
        await button(wrapper, 'Aktifkan')?.trigger('click')
        expect(router.post).toHaveBeenCalledWith('/admin.schemes.activate/4', {}, expect.anything())
    })

    it('empty state membedakan daftar kosong dan hasil filter kosong', () => {
        expect(mountPage([]).text()).toContain('Belum ada skema')
        expect(
            mountPage([], { filters: { search: 'xyz', type: '', status: '' } }).text()
        ).toContain('Tidak ada skema yang cocok')
    })
})
