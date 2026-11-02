import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }))

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

import FilterSelect from '@/Components/app/FilterSelect.vue'
import UserIndexPage from '@/Pages/Identity/UserIndexPage.vue'
import type { UserIndexFilters, UserIndexPageProps, UserRowData } from '@/types/identity'

const pendingRow: UserRowData = {
    id: 7,
    name: 'Dr. Maya Sari',
    email: 'maya.sari@itk.ac.id',
    nidn: '0654321098',
    organization: 'FSTI — Matematika',
    roles: [{ value: 'researcher', label: 'Peneliti' }],
    status: { active: true, verification: { value: 'pending', label: 'Menunggu Verifikasi' } },
    showUrl: '/admin/users/7',
}

const invitedRow: UserRowData = {
    ...pendingRow,
    id: 8,
    name: 'Ir. Joko Susilo',
    nidn: null,
    status: { active: false, verification: null },
    showUrl: '/admin/users/8',
}

const noFilters: UserIndexFilters = { search: '', role: '', account: '', verification: '' }

const props = (
    rows: UserRowData[],
    overrides: Partial<UserIndexPageProps> = {}
): UserIndexPageProps => ({
    users: {
        data: rows,
        current_page: 1,
        last_page: 1,
        per_page: 20,
        total: rows.length,
        from: rows.length > 0 ? 1 : null,
        to: rows.length,
        prev_page_url: null,
        next_page_url: null,
    },
    filters: noFilters,
    roleOptions: [{ value: 'operator', label: 'Operator' }],
    accountOptions: [{ value: 'inactive', label: 'Nonaktif' }],
    verificationOptions: [{ value: 'pending', label: 'Menunggu Verifikasi' }],
    awaitingVerification: 2,
    can: { verify: true, manageInvitations: true },
    ...overrides,
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (rows: UserRowData[], overrides: Partial<UserIndexPageProps> = {}) =>
    mount(UserIndexPage, { props: props(rows, overrides), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.get.mockClear()
})

describe('UserIndexPage', () => {
    it('menampilkan kolom Nama, Email, NIDN, Organisasi, Role, Status', () => {
        const wrapper = mountPage([pendingRow])
        const headers = wrapper.findAll('th').map((th) => th.text())

        expect(headers).toEqual(['Nama', 'Email', 'NIDN', 'Organisasi', 'Role', 'Status', ''])
        expect(wrapper.text()).toContain('Peneliti')
        expect(wrapper.text()).toContain('Aktif')
        expect(wrapper.text()).toContain('Menunggu Verifikasi')
    })

    it('NIDN kosong tampil "—"', () => {
        const cells = mountPage([invitedRow])
            .findAll('td')
            .map((td) => td.text())

        expect(cells[2]).toBe('—')
        expect(cells[5]).toBe('Nonaktif')
    })

    it('tombol "Antrian Verifikasi NIDN (2)" hanya bila can.verify', () => {
        const wrapper = mountPage([pendingRow])
        const link = wrapper.find('a[href="/admin.verifications.index"]')

        expect(link.text()).toContain('Antrian Verifikasi NIDN (2)')
        expect(
            mountPage([pendingRow], { can: { verify: false, manageInvitations: true } })
                .find('a[href="/admin.verifications.index"]')
                .exists()
        ).toBe(false)
    })

    it('tombol "Undangan Registrasi" hanya bila can.manageInvitations', () => {
        const wrapper = mountPage([pendingRow])

        expect(wrapper.find('a[href="/admin.invitations.index"]').text()).toContain(
            'Undangan Registrasi'
        )
        expect(
            mountPage([pendingRow], { can: { verify: true, manageInvitations: false } })
                .find('a[href="/admin.invitations.index"]')
                .exists()
        ).toBe(false)
    })

    it('[IAM-DEACT-07] tidak ada aksi hapus maupun nonaktifkan di baris', () => {
        const row = mountPage([pendingRow]).findAll('tbody tr')[0]

        expect(row.text()).not.toContain('Hapus')
        expect(row.text()).not.toContain('Nonaktifkan')
        expect(row.findAll('a').map((a) => a.attributes('href'))).toEqual(['/admin/users/7'])
        expect(row.find('a').text()).toContain('Detail')
    })

    it('mengubah filter Role memanggil router.get ke admin.users.index tanpa page', async () => {
        const wrapper = mountPage([pendingRow])
        const role = wrapper
            .findAllComponents(FilterSelect)
            .find((select) => select.props('label') === 'Role')

        role?.vm.$emit('update:modelValue', 'operator')
        await nextTick()

        expect(router.get).toHaveBeenCalledWith(
            '/admin.users.index',
            { role: 'operator' },
            expect.objectContaining({ preserveState: true })
        )
    })

    it('banner ketua pengajuan aktif tampil', () => {
        expect(mountPage([pendingRow]).text()).toContain(
            'User yang masih menjadi ketua pengajuan aktif tidak dapat dinonaktifkan sebelum kepemilikannya dialihkan.'
        )
    })

    it('EmptyState saat tidak ada user', () => {
        expect(mountPage([]).text()).toContain('Belum ada user.')
        expect(mountPage([], { filters: { ...noFilters, role: 'operator' } }).text()).toContain(
            'Tidak ada user yang cocok dengan filter.'
        )
    })
})
