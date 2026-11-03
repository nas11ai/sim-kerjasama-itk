import { flushPromises, mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), patch: vi.fn(), delete: vi.fn() },
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

import FormIndexPage from '@/Pages/FormEngine/FormIndexPage.vue'
import type { FormIndexPageProps, FormRowData } from '@/types/form-engine'

const formRow = (overrides: Partial<FormRowData> = {}): FormRowData => ({
    id: 3,
    title: 'Form Proposal Penelitian — LPPM ITK',
    typeName: 'Penelitian',
    fieldCount: 18,
    activeSubmissionCount: 42,
    submissionCount: 45,
    isActive: true,
    ...overrides,
})

const props = (
    rows: FormRowData[],
    overrides: Partial<FormIndexPageProps> = {}
): FormIndexPageProps => ({
    forms: {
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
    typeOptions: [{ value: '2', label: 'Penelitian' }],
    statusOptions: [
        { value: 'active', label: 'Aktif' },
        { value: 'inactive', label: 'Nonaktif' },
    ],
    ...overrides,
})

const route = (name: string, id?: number) => (id === undefined ? `/${name}` : `/${name}/${id}`)

const mountPage = (rows: FormRowData[], overrides: Partial<FormIndexPageProps> = {}) =>
    mount(FormIndexPage, {
        props: props(rows, overrides),
        global: { mocks: { route } },
    })

const clickIn = async (wrapper: VueWrapper, label: string) => {
    await wrapper
        .findAll('button')
        .find((b) => b.text().trim() === label)
        ?.trigger('click')
    await flushPromises()
}

const bodyButton = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('FormEngine/FormIndexPage', () => {
    it('menggambar judul, subjudul, kolom, dan catatan kaki', () => {
        const text = mountPage([formRow()]).text()

        expect(text).toContain('Form Builder')
        expect(text).toContain(
            'Kelola form pengajuan & monev — susunan field dapat diubah sewaktu-waktu.'
        )
        for (const column of ['Nama Form', 'Tipe', 'Field Aktif', 'Pengajuan Aktif', 'Status']) {
            expect(text).toContain(column)
        }
        expect(text).toContain('Form Proposal Penelitian — LPPM ITK')
        // W76 — Form yang dipakai dinonaktifkan, bukan dihapus (BR-FE-19).
        expect(text).toContain('Form yang pernah dipakai pengajuan tidak dapat dihapus')
        expect(text).toContain('Menonaktifkan form memerlukan konfirmasi.')
    })

    it('"Buat Form Baru" dan "Edit" menaut ke rute form', () => {
        const wrapper = mountPage([formRow()])

        expect(wrapper.find('a[href="/admin.forms.create"]').text()).toContain('Buat Form Baru')
        expect(wrapper.find('a[href="/admin.forms.edit/3"]').text()).toContain('Edit')
    })

    it('label tombol status mengikuti isActive', () => {
        const labels = (row: FormRowData) =>
            mountPage([row])
                .findAll('button')
                .map((b) => b.text())

        expect(labels(formRow())).toContain('Nonaktifkan')
        expect(labels(formRow({ isActive: false }))).toContain('Aktifkan')
        expect(labels(formRow({ isActive: false }))).not.toContain('Nonaktifkan')
    })

    it('empty state membedakan daftar kosong dan hasil filter kosong', () => {
        expect(mountPage([]).text()).toContain('Belum ada form')
        expect(
            mountPage([], { filters: { search: 'xyz', type: '', status: '' } }).text()
        ).toContain('Tidak ada form yang cocok')
    })

    it('[FE-DELETE-01] hapus form berpengajuan membuka penjelasan berangka, bukan konfirmasi', async () => {
        const wrapper = mountPage([formRow({ submissionCount: 45 })])
        await clickIn(wrapper, 'Hapus')

        expect(document.body.textContent).toContain('Form tidak bisa dihapus')
        expect(document.body.textContent).toContain('Form ini dipakai 45 pengajuan')
        expect(document.body.textContent).not.toContain('Hapus form?')
        expect(router.delete).not.toHaveBeenCalled()
    })

    it('[FE-DELETE-02] satu draft pun membuka penjelasan', async () => {
        const wrapper = mountPage([formRow({ submissionCount: 1, activeSubmissionCount: 1 })])
        await clickIn(wrapper, 'Hapus')

        expect(document.body.textContent).toContain('Form ini dipakai 1 pengajuan')
    })

    it('penjelasan menawarkan Nonaktifkan Form hanya bila form masih aktif', async () => {
        const active = mountPage([formRow()])
        await clickIn(active, 'Hapus')
        expect(bodyButton('Nonaktifkan Form')).toBeDefined()

        document.body.innerHTML = ''
        const inactive = mountPage([formRow({ isActive: false })])
        await clickIn(inactive, 'Hapus')
        expect(bodyButton('Nonaktifkan Form')).toBeUndefined()
    })

    it('[FE-DELETE-04] form kosong dikonfirmasi lalu dihapus', async () => {
        const wrapper = mountPage([formRow({ submissionCount: 0, activeSubmissionCount: 0 })])
        await clickIn(wrapper, 'Hapus')

        expect(document.body.textContent).toContain('Hapus form?')
        bodyButton('Hapus Form')?.click()
        await flushPromises()
        expect(router.delete).toHaveBeenCalledWith('/admin.forms.destroy/3', expect.anything())
    })

    it('[FE-DELETE-05] nonaktifkan dikonfirmasi lalu memanggil route active', async () => {
        const wrapper = mountPage([formRow()])
        await clickIn(wrapper, 'Nonaktifkan')

        expect(document.body.textContent).toContain('Nonaktifkan form?')
        expect(document.body.textContent).toContain('42 pengajuan aktif tetap berjalan dan terbaca.')
        bodyButton('Nonaktifkan Form')?.click()
        await flushPromises()
        expect(router.patch).toHaveBeenCalledWith(
            '/admin.forms.active/3',
            { active: false },
            expect.anything()
        )
    })

    it('mengaktifkan form nonaktif memanggil route active dengan true', async () => {
        const wrapper = mountPage([formRow({ isActive: false })])
        await clickIn(wrapper, 'Aktifkan')

        expect(document.body.textContent).toContain('Aktifkan form?')
        bodyButton('Aktifkan Form')?.click()
        await flushPromises()
        expect(router.patch).toHaveBeenCalledWith(
            '/admin.forms.active/3',
            { active: true },
            expect.anything()
        )
    })
})
