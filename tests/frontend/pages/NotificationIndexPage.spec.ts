import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn(), delete: vi.fn() },
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

import ConfirmDialog from '@/Components/app/ConfirmDialog.vue'
import IndexPage from '@/Pages/Notification/IndexPage.vue'
import type {
    NotificationFilter,
    NotificationIndexPageProps,
    NotificationRow,
} from '@/types/notification'

const row: NotificationRow = {
    id: 'n-1',
    title: 'Penugasan review baru',
    message: 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
    category: 'review',
    categoryLabel: 'Review',
    createdAt: '2026-07-03T10:00:00',
    isRead: false,
}

const props = (
    rows: NotificationRow[],
    filter: NotificationFilter = 'all',
    unreadCount = 1
): NotificationIndexPageProps => ({
    notifications: {
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
    filter,
    unreadCount,
})

const route = (name: string, param?: string) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (...args: Parameters<typeof props>) =>
    mount(IndexPage, { props: props(...args), global: { mocks: { route } } })

const button = (wrapper: ReturnType<typeof mountPage>, label: string) =>
    wrapper.findAll('button').find((b) => b.text() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.get.mockClear()
    router.post.mockClear()
    router.delete.mockClear()
})

describe('Notification/IndexPage', () => {
    it('[NOT-INAPP-03] memuat halaman tidak mengirim read', () => {
        mountPage([row])

        expect(router.post).not.toHaveBeenCalled()
    })

    it('[NOT-INAPP-03] Buka mengirim read untuk notifikasi itu', async () => {
        const wrapper = mountPage([row])

        await button(wrapper, 'Buka')?.trigger('click')

        expect(router.post).toHaveBeenCalledWith('/notifications.read/n-1')
    })

    it('[NOT-INAPP-02] Hapus lewat ConfirmDialog', async () => {
        const wrapper = mountPage([row])

        await button(wrapper, 'Hapus')?.trigger('click')
        const dialog = wrapper.findComponent(ConfirmDialog)
        expect(dialog.props('open')).toBe(true)
        expect(router.delete).not.toHaveBeenCalled()

        dialog.vm.$emit('confirm')
        await nextTick()

        expect(router.delete).toHaveBeenCalledWith(
            '/notifications.destroy/n-1',
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[NOT-INAPP-04] Tandai Semua Dibaca mengirim read-all', async () => {
        const wrapper = mountPage([row])

        await button(wrapper, 'Tandai Semua Dibaca')?.trigger('click')

        expect(router.post).toHaveBeenCalledWith(
            '/notifications.read-all',
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('Tandai Semua Dibaca tersembunyi saat unreadCount 0', () => {
        expect(button(mountPage([{ ...row, isRead: true }], 'all', 0), 'Tandai Semua Dibaca')).toBe(
            undefined
        )
    })

    it('chip aktif mengikuti filter dan memfilter lewat server', async () => {
        const wrapper = mountPage([row], 'unread')

        expect(button(wrapper, 'Belum dibaca')?.attributes('aria-pressed')).toBe('true')
        expect(button(wrapper, 'Semua')?.attributes('aria-pressed')).toBe('false')

        await button(wrapper, 'Review')?.trigger('click')
        await nextTick()

        expect(router.get).toHaveBeenCalledWith(
            '/notifications.index',
            { filter: 'review' },
            expect.objectContaining({ preserveState: true, replace: true })
        )
    })

    it('EmptyState saat daftar kosong', () => {
        expect(mountPage([], 'all', 0).text()).toContain('Belum ada notifikasi')
    })

    it('menampilkan dua banner "Bagaimana Notifikasi Bekerja"', () => {
        const text = mountPage([row]).text()

        expect(text).toContain('Bagaimana Notifikasi Bekerja')
        expect(text).toContain('bukan saat halaman ini dibuka')
        expect(text).toContain('cukup muncul di sini')
    })
})
