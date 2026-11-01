import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, page } = vi.hoisted(() => ({
    router: { post: vi.fn() },
    page: { props: { auth: { user: null as { name: string } | null } } },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: () => page,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        name: 'AppLayout',
        setup:
            (_, { slots }) =>
            () =>
                h('div', { class: 'app-layout' }, slots.default?.()),
    }),
}))
vi.mock('@/Layouts/AuthLayout.vue', () => ({
    default: defineComponent({
        name: 'AuthLayout',
        props: { wide: Boolean },
        setup:
            (_, { slots }) =>
            () =>
                h('div', { class: 'auth-layout' }, slots.default?.()),
    }),
}))

import ShowPage from '@/Pages/Announcement/ShowPage.vue'
import type { AnnouncementDetail } from '@/types/announcement'

const detail: AnnouncementDetail = {
    id: 7,
    title: 'Format berkas baru',
    contentHtml: '<p><strong>tebal</strong></p>',
    publishedAt: '2026-10-30T08:00:00+08:00',
    expiresAt: null,
    author: 'Operator LPPM',
    files: [{ id: 1, name: 'panduan.pdf', url: '/storage/announcements/p.pdf', size: 2048 }],
    isRead: false,
}

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (announcement: AnnouncementDetail) =>
    mount(ShowPage, { props: { announcement }, global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.post.mockClear()
    page.props.auth.user = { name: 'Budi' }
})

describe('Announcement/ShowPage', () => {
    it('membuka pengumuman belum dibaca mengirim markRead sekali', () => {
        mountPage(detail)

        expect(router.post).toHaveBeenCalledTimes(1)
        expect(router.post).toHaveBeenCalledWith(
            '/user.announcements.markRead/7',
            {},
            expect.objectContaining({ preserveScroll: true, preserveState: true })
        )
    })

    it('pengumuman sudah dibaca atau dibuka tamu tidak mengirim markRead', () => {
        mountPage({ ...detail, isRead: true })
        page.props.auth.user = null
        mountPage({ ...detail, isRead: null })

        expect(router.post).not.toHaveBeenCalled()
    })

    it('isi rich text dirender sebagai format', () => {
        const wrapper = mountPage(detail)

        expect(wrapper.find('.announcement-content strong').text()).toBe('tebal')
        expect(wrapper.text()).toContain('panduan.pdf')
    })

    it('tamu memakai AuthLayout, pengguna login memakai AppLayout', () => {
        expect(mountPage(detail).find('.app-layout').exists()).toBe(true)

        page.props.auth.user = null
        const guest = mountPage({ ...detail, isRead: null })
        expect(guest.find('.auth-layout').exists()).toBe(true)
        expect(guest.find('.app-layout').exists()).toBe(false)
    })
})
