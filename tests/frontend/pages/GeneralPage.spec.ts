import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { post: vi.fn(), put: vi.fn(), delete: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: defineComponent({ setup: () => () => null }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import OptionSelect from '@/Components/app/OptionSelect.vue'
import GeneralPage from '@/Pages/SystemConfig/GeneralPage.vue'
import type { GeneralPageProps, ReferenceCategoryData } from '@/types/system-config'

const category = (overrides: Partial<ReferenceCategoryData>): ReferenceCategoryData => ({
    key: 'scheme-types',
    label: 'Tipe Skema',
    group: 'scheme',
    usedBy: 'Skema',
    editable: true,
    deactivatable: true,
    items: [{ id: 1, name: 'Penelitian Dasar', isActive: true, usageCount: 2 }],
    ...overrides,
})

const props = (): GeneralPageProps => ({
    categories: [
        category({}),
        category({
            key: 'submission-types',
            label: 'Tipe Submission',
            usedBy: 'Skema, Submission, Review, Monev',
            editable: false,
        }),
        category({
            key: 'budget-components',
            label: 'Komponen Anggaran',
            group: 'other',
            usedBy: 'Rencana anggaran pengajuan',
        }),
        category({
            key: 'output-types',
            label: 'Tipe Luaran',
            group: 'output',
            usedBy: 'luaran dan target luaran pengajuan',
            items: [
                { id: 5, name: 'Jurnal', isActive: true, usageCount: 0 },
                { id: 6, name: 'HKI', isActive: false, usageCount: 1 },
            ],
        }),
    ],
    notificationChannels: [
        { eventType: 'RevisionResolved', channels: ['database'], declared: true },
    ],
    channelOptions: [
        { value: 'database', label: 'In-app' },
        { value: 'mail', label: 'Email' },
    ],
})

const route = (name: string, params?: string) =>
    params === undefined ? `/${name}` : `/${name}/${params}`

const mountPage = () =>
    mount(GeneralPage, { props: props(), global: { mocks: { route } }, attachTo: document.body })

beforeEach(() => {
    vi.stubGlobal('route', route)
    document.body.innerHTML = ''
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('SystemConfig/GeneralPage', () => {
    it('menggambar judul, subjudul, tab, dan kartu kategori', () => {
        const text = mountPage().text()

        expect(text).toContain('Konfigurasi Umum')
        expect(text).toContain('Kelola data acuan yang dipakai di seluruh aplikasi.')
        for (const tab of ['Semua Kategori', 'Referensi Luaran', 'Referensi Skema', 'Lainnya']) {
            expect(text).toContain(tab)
        }
        for (const card of ['Referensi Luaran', 'Referensi Skema & Submission', 'Anggaran']) {
            expect(text).toContain(card)
        }
        expect(text).toContain('2 item · dipakai oleh luaran dan target luaran pengajuan')
        expect(text).toContain('Nonaktifkan item hanya jika tidak sedang dipakai data aktif.')
    })

    it('tab Referensi Luaran hanya menampilkan tipe luaran', async () => {
        const wrapper = mountPage()
        const tab = wrapper
            .findAll('button[role="tab"]')
            .find((t) => t.text() === 'Referensi Luaran')
        await tab?.trigger('mousedown', { button: 0 })
        await flushPromises()

        expect(wrapper.text()).toContain('Tipe Luaran')
        expect(wrapper.text()).not.toContain('Komponen Anggaran')
        expect(wrapper.text()).not.toContain('Referensi Skema & Submission')
    })

    it('channel notifikasi tampil di Semua Kategori dan Lainnya saja', async () => {
        const wrapper = mountPage()
        expect(wrapper.text()).toContain('Channel Notifikasi')
        expect(wrapper.text()).toContain('RevisionResolved')

        const select = async (label: string) => {
            const tab = wrapper.findAll('button[role="tab"]').find((t) => t.text() === label)
            await tab?.trigger('mousedown', { button: 0 })
            await flushPromises()
        }
        await select('Referensi Skema')
        expect(wrapper.text()).not.toContain('Channel Notifikasi')
        await select('Lainnya')
        expect(wrapper.text()).toContain('Channel Notifikasi')
        expect(wrapper.text()).toContain('Komponen Anggaran')
        expect(wrapper.text()).not.toContain('Tipe Luaran')
    })

    it('Kelola membuka daftar item kategori itu', async () => {
        const wrapper = mountPage()
        const manage = wrapper.findAll('button').filter((b) => b.text().trim() === 'Kelola')
        await manage.at(0)?.trigger('click')
        await flushPromises()

        expect(document.body.textContent).toContain('HKI')
        expect(document.body.textContent).toContain('dipakai 1')
    })

    it('[SC-CONFIG-06] tambah item mengirim ke kategori terpilih', async () => {
        const wrapper = mountPage()
        // Tipe Submission tidak bisa ditambah dari layar (BR-SC-02), jadi tidak ditawarkan.
        const select = wrapper.findComponent(OptionSelect)
        expect(select.props('options').map((o) => o.label)).toEqual([
            'Tipe Skema',
            'Komponen Anggaran',
            'Tipe Luaran',
        ])

        select.vm.$emit('update:modelValue', 'output-types')
        await flushPromises()
        await wrapper.find('#reference-new-name').setValue('Sertifikat Halal')
        await wrapper
            .findAll('button')
            .find((b) => b.text().trim() === 'Tambah Item')
            ?.trigger('click')
        await flushPromises()

        expect(router.post).toHaveBeenCalledWith(
            '/admin.reference.store/output-types',
            { name: 'Sertifikat Halal' },
            expect.anything()
        )
    })
})
