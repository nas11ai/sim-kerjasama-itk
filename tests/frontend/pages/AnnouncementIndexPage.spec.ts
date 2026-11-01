import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
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

import IndexPage from '@/Pages/Announcement/IndexPage.vue'
import type { AnnouncementSummary } from '@/types/announcement'

const summary: AnnouncementSummary = {
    id: 3,
    title: 'Libur nasional',
    excerpt: 'Kantor LPPM tutup',
    publishedAt: '2026-10-30T08:00:00+08:00',
    attachmentCount: 2,
    isRead: false,
}

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (rows: AnnouncementSummary[]) =>
    mount(IndexPage, {
        props: {
            announcements: {
                data: rows,
                current_page: 1,
                last_page: 1,
                per_page: 10,
                total: rows.length,
                from: rows.length > 0 ? 1 : null,
                to: rows.length,
                prev_page_url: null,
                next_page_url: null,
            },
        },
        global: { mocks: { route } },
    })

beforeEach(() => vi.stubGlobal('route', route))

describe('Announcement/IndexPage', () => {
    it('pengumuman belum dibaca diberi badge Baru', () => {
        const wrapper = mountPage([summary, { ...summary, id: 4, title: 'Lama', isRead: true }])

        const cards = wrapper.findAll('article')
        expect(cards[0].text()).toContain('Baru')
        expect(cards[1].text()).not.toContain('Baru')
        expect(wrapper.find('a[href="/announcements.detail/3"]').exists()).toBe(true)
        expect(cards[0].text()).toContain('2 lampiran')
    })

    it('daftar kosong menampilkan pesan', () => {
        expect(mountPage([]).text()).toContain('Belum ada pengumuman untuk Anda.')
    })
})
