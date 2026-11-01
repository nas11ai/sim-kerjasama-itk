import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { get: vi.fn(), delete: vi.fn() } }))

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

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import ManageIndexPage from '@/Pages/Announcement/ManageIndexPage.vue'
import type { AnnouncementManageIndexPageProps, ManagedAnnouncementRow } from '@/types/announcement'

const row: ManagedAnnouncementRow = {
    id: 5,
    title: 'Format berkas baru',
    audienceLabel: 'Dosen, Operator LPPM',
    expiresAt: null,
    expired: false,
    createdAt: '2026-10-30T08:00:00+08:00',
    author: 'Operator LPPM',
}

const props = (rows: ManagedAnnouncementRow[], search = ''): AnnouncementManageIndexPageProps => ({
    announcements: {
        data: rows,
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: rows.length,
        from: rows.length > 0 ? 1 : null,
        to: rows.length,
        prev_page_url: null,
        next_page_url: null,
    },
    filters: { search },
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (rows: ManagedAnnouncementRow[], search = '') =>
    mount(ManageIndexPage, { props: props(rows, search), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.delete.mockClear()
})

describe('Announcement/ManageIndexPage', () => {
    it('daftar kosong menampilkan "Belum ada pengumuman."', () => {
        expect(mountPage([]).text()).toContain('Belum ada pengumuman.')
    })

    it('daftar kosong saat dicari menampilkan pesan pencarian', () => {
        expect(mountPage([], 'hibah').text()).toContain('Tidak ada pengumuman yang cocok.')
    })

    it('baris kedaluwarsa diberi badge Kedaluwarsa', () => {
        const wrapper = mountPage([
            { ...row, expired: true, expiresAt: '2026-10-01T23:59:59+08:00' },
        ])

        expect(wrapper.text()).toContain('Kedaluwarsa')
        expect(mountPage([row]).text()).toContain('Tanpa batas')
    })

    it('hapus meminta konfirmasi lalu router.delete', async () => {
        const wrapper = mountPage([row])
        const hapus = wrapper.findAll('button').find((b) => b.text() === 'Hapus')

        await hapus?.trigger('click')
        const dialog = wrapper.findComponent(ConfirmDialog)
        expect(dialog.props('open')).toBe(true)
        expect(router.delete).not.toHaveBeenCalled()

        dialog.vm.$emit('confirm')
        await nextTick()

        expect(router.delete).toHaveBeenCalledWith(
            '/admin.announcements.destroy/5',
            expect.objectContaining({ preserveScroll: true })
        )
    })
})
