import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

import WelcomePage from '@/Pages/Welcome/WelcomePage.vue'
import type { AnnouncementSummary } from '@/types/announcement'

const summary: AnnouncementSummary = {
    id: 12,
    title: 'Pendaftaran hibah dibuka',
    excerpt: 'Silakan mendaftar sebelum 30 November.',
    publishedAt: '2026-10-30T08:00:00+08:00',
    attachmentCount: 2,
    isRead: null,
}

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (announcements: AnnouncementSummary[], canRegister = true) =>
    mount(WelcomePage, {
        props: { canRegister, announcements },
        global: { mocks: { route } },
    })

beforeEach(() => vi.stubGlobal('route', route))

describe('Welcome/WelcomePage', () => {
    it('[K7] menautkan Masuk dan Daftar', () => {
        const hrefs = mountPage([])
            .findAll('a')
            .map((link) => link.attributes('href'))

        expect(hrefs).toContain('/login')
        expect(hrefs).toContain('/register')
    })

    it('[K7] tanpa pendaftaran tidak menawarkan Daftar', () => {
        const hrefs = mountPage([], false)
            .findAll('a')
            .map((link) => link.attributes('href'))

        expect(hrefs).not.toContain('/register')
    })

    it('[K7] menampilkan pengumuman publik dengan tautan detail', () => {
        const wrapper = mountPage([summary])
        const link = wrapper.find('a[href="/announcements.detail/12"]')

        expect(link.text()).toContain('Pendaftaran hibah dibuka')
        expect(link.text()).toContain('2 lampiran')
    })

    it('[K7] menyatakan bila belum ada pengumuman', () => {
        expect(mountPage([]).text()).toContain('Belum ada pengumuman publik.')
    })
})
