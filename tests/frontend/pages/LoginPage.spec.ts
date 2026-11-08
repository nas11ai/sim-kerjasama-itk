import { mount } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends object>(data: T) =>
        reactive({ ...data, errors: {}, processing: false, post: vi.fn(), reset: vi.fn() }),
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))
vi.mock('@/composables/useFlashToasts', () => ({ useFlashToasts: vi.fn() }))

import LoginPage from '@/Pages/Auth/LoginPage.vue'
import type { AnnouncementSummary } from '@/types/announcement'

const summary: AnnouncementSummary = {
    id: 12,
    title: 'Pendaftaran hibah dibuka',
    excerpt: 'Silakan mendaftar',
    publishedAt: '2026-10-30T08:00:00+08:00',
    attachmentCount: 0,
    isRead: null,
}

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (announcements: AnnouncementSummary[]) =>
    mount(LoginPage, {
        props: { canResetPassword: true, status: null, announcements },
        global: { mocks: { route } },
    })

beforeEach(() => vi.stubGlobal('route', route))

describe('Auth/LoginPage', () => {
    it('panel pengumuman tersembunyi bila kosong', () => {
        const wrapper = mountPage([])

        expect(wrapper.find('[aria-labelledby="public-announcements"]').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Pengumuman')
    })

    it('item panel menaut ke announcements.detail', () => {
        const wrapper = mountPage([summary])

        expect(wrapper.find('[aria-labelledby="public-announcements"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/announcements.detail/12"]').text()).toContain(
            'Pendaftaran hibah dibuka'
        )
    })
})
