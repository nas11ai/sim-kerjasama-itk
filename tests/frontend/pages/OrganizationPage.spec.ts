import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn(), reload: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
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

import OrganizationPage from '@/Pages/SystemConfig/OrganizationPage.vue'
import type { OrganizationPageProps, OrganizationUnitData } from '@/types/system-config'

const unit = (overrides: Partial<OrganizationUnitData> = {}): OrganizationUnitData => ({
    id: 3,
    name: 'Teknik Mesin',
    type: 'study_program',
    typeLabel: 'Program Studi',
    isActive: true,
    parentId: 2,
    census: {
        directProfiles: 14,
        directActiveUsers: 14,
        subtreeProfiles: 14,
        subtreeActiveUsers: 14,
        unitsWithActiveUsers: 0,
        pendingInvitations: 0,
    },
    canDeactivate: false,
    canDelete: false,
    ...overrides,
})

const props = (selected: OrganizationUnitData | null): OrganizationPageProps => ({
    tree: [
        {
            id: 1,
            name: 'ITK',
            typeLabel: 'Institusi',
            isActive: true,
            parentId: null,
            depth: 0,
            hasChildren: true,
        },
        {
            id: 2,
            name: 'FTI',
            typeLabel: 'Fakultas',
            isActive: true,
            parentId: 1,
            depth: 1,
            hasChildren: true,
        },
        {
            id: 3,
            name: 'Teknik Mesin',
            typeLabel: 'Program Studi',
            isActive: true,
            parentId: 2,
            depth: 2,
            hasChildren: false,
        },
    ],
    selected,
    typeOptions: [{ value: 'unit', label: 'Unit Kerja' }],
    parentOptions: [{ value: '1', label: 'ITK' }],
})

const route = (name: string) => `/${name}`

const mountPage = (selected: OrganizationUnitData | null) =>
    mount(OrganizationPage, { props: props(selected), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    Object.values(router).forEach((fn) => fn.mockClear())
})

describe('OrganizationPage', () => {
    it('menampilkan judul dan subjudul frame', () => {
        const text = mountPage(null).text()

        expect(text).toContain('Struktur Organisasi')
        expect(text).toContain(
            'Susun unit kerja secara berjenjang — klik untuk membuka atau menutup cabang.'
        )
    })

    it('panel kosong saat belum ada unit terpilih', () => {
        expect(mountPage(null).text()).toContain('Pilih unit di pohon untuk melihat rinciannya.')
    })

    it('klik node memuat ulang unit terpilih saja', async () => {
        const wrapper = mountPage(null)
        const node = wrapper.findAll('[role="treeitem"]').find((n) => n.text().includes('FTI'))

        await node?.find('button[data-select]').trigger('click')

        expect(router.get).toHaveBeenCalledWith(
            '/admin.organizations.index',
            { unit: 2 },
            expect.objectContaining({ only: ['selected'], preserveState: true, replace: true })
        )
    })

    it('chevron menutup cabang beserta seluruh keturunannya', async () => {
        const wrapper = mountPage(null)
        const itk = wrapper.findAll('[role="treeitem"]')[0]

        await itk.find('button[aria-expanded]').trigger('click')

        expect(wrapper.findAll('[role="treeitem"]').map((n) => n.text())).toEqual([
            expect.stringContaining('ITK'),
        ])
    })

    it('unit terpilih menampilkan rincian dan sensusnya', () => {
        const text = mountPage(unit()).text()

        expect(text).toContain('Program Studi')
        expect(text).toContain('14 — seluruhnya aktif')
        expect(text).toContain(
            'Nonaktifkan dan Hapus adalah dua operasi dengan ambang berbeda: nonaktif melihat user aktif, hapus melihat profil apa pun.'
        )
    })

    it('node terpilih ditandai aria-selected', () => {
        const nodes = mountPage(unit()).findAll('[role="treeitem"]')

        expect(nodes.map((n) => n.attributes('aria-selected'))).toEqual(['false', 'false', 'true'])
    })
})
