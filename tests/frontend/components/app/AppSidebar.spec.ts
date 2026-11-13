import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'

import AppSidebar from '@/Components/app/AppSidebar.vue'
import { SidebarProvider, SidebarTrigger } from '@/Components/ui/sidebar'

const { post, listeners } = vi.hoisted(() => ({
    post: vi.fn(),
    listeners: [] as { type: string; callback: () => void }[],
}))

const page = {
    props: {
        auth: {
            user: {
                name: 'Budi Peneliti',
                email: 'budi@itk.ac.id',
                permissions: ['submissions.view-own', 'submissions.create'],
            },
        },
        navigation: { hasOutputSubmission: false },
    },
}

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: {
        post,
        on(type: string, callback: () => void) {
            const listener = { type, callback }
            listeners.push(listener)
            return () => listeners.splice(listeners.indexOf(listener), 1)
        },
    },
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

let currentPattern = ''
const routeStub = (name?: string) =>
    name ? `/${name}` : { current: (pattern: string) => pattern === currentPattern }

let wrapper: ReturnType<typeof mount>

const mountSidebar = () =>
    mount(
        defineComponent({
            render: () => h(SidebarProvider, null, () => [h(SidebarTrigger), h(AppSidebar)]),
        }),
        { global: { mocks: { route: routeStub } }, attachTo: document.body }
    )

const button = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

const menuButton = (label: string) =>
    Array.from(document.querySelectorAll('[data-sidebar="menu-button"]')).find((el) =>
        el.textContent?.includes(label)
    )

beforeEach(() => vi.stubGlobal('route', routeStub))

afterEach(() => {
    wrapper.unmount()
    post.mockReset()
    currentPattern = ''
    vi.unstubAllGlobals()
})

describe('AppSidebar', () => {
    it('menampilkan menu sesuai permission peneliti', () => {
        wrapper = mountSidebar()

        expect(wrapper.text()).toContain('Beranda')
        expect(wrapper.text()).toContain('Daftar Pengajuan')
        expect(wrapper.text()).not.toContain('Semua Pengajuan')
    })

    it('menandai menu aktif lewat activePattern', () => {
        currentPattern = 'user.submissions.*'
        wrapper = mountSidebar()

        expect(menuButton('Daftar Pengajuan')?.getAttribute('data-active')).toBe('true')
        expect(menuButton('Beranda')?.getAttribute('data-active')).not.toBe('true')
    })

    it('menampilkan nama dan email pengguna di footer', () => {
        wrapper = mountSidebar()

        expect(wrapper.text()).toContain('Budi Peneliti')
        expect(wrapper.text()).toContain('budi@itk.ac.id')
    })

    it('keluar hanya setelah konfirmasi dialog', async () => {
        wrapper = mountSidebar()

        await wrapper.get('[aria-label="Keluar"]').trigger('click')
        await flushPromises()

        expect(document.body.textContent).toContain('Keluar dari SIMPAS?')
        expect(post).not.toHaveBeenCalled()

        button('Keluar')?.click()

        expect(post).toHaveBeenCalledWith('/logout', {}, expect.anything())
    })

    it('tidak mengirim logout dua kali selama permintaan berjalan', async () => {
        wrapper = mountSidebar()
        await wrapper.get('[aria-label="Keluar"]').trigger('click')
        await flushPromises()

        button('Keluar')?.click()
        await flushPromises()
        button('Keluar')?.click()

        expect(post).toHaveBeenCalledTimes(1)
        expect(button('Keluar')?.disabled).toBe(true)

        post.mock.calls[0][2].onFinish()
        await flushPromises()

        expect(button('Keluar')?.disabled).toBe(false)
    })
})

describe('AppSidebar di layar sempit', () => {
    beforeEach(() =>
        vi.stubGlobal('matchMedia', (query: string) => ({
            matches: true,
            media: query,
            addEventListener: () => {},
            removeEventListener: () => {},
        }))
    )

    it('menutup drawer setelah navigasi', async () => {
        wrapper = mountSidebar()
        await wrapper.get('[data-sidebar="trigger"]').trigger('click')
        await flushPromises()

        expect(document.querySelector('[data-mobile="true"]')).not.toBeNull()

        listeners.filter((l) => l.type === 'navigate').forEach((l) => l.callback())
        await flushPromises()

        expect(document.querySelector('[data-mobile="true"]')).toBeNull()
    })

    // Kunjungan ke URL yang sama hanya replace, tanpa event navigate.
    it('menutup drawer saat menu halaman aktif diketuk', async () => {
        currentPattern = 'user.submissions.*'
        wrapper = mountSidebar()
        await wrapper.get('[data-sidebar="trigger"]').trigger('click')
        await flushPromises()

        const link = menuButton('Daftar Pengajuan')
        expect(link?.getAttribute('data-active')).toBe('true')
        link?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
        await flushPromises()

        expect(document.querySelector('[data-mobile="true"]')).toBeNull()
    })

    it('menutup drawer saat tautan profil di footer diketuk', async () => {
        wrapper = mountSidebar()
        await wrapper.get('[data-sidebar="trigger"]').trigger('click')
        await flushPromises()

        document
            .querySelector('[aria-label="Pengaturan"]')
            ?.dispatchEvent(new MouseEvent('click', { bubbles: true }))
        await flushPromises()

        expect(document.querySelector('[data-mobile="true"]')).toBeNull()
    })

    it('berhenti mendengarkan navigasi setelah dilepas', () => {
        wrapper = mountSidebar()
        wrapper.unmount()
        wrapper = mountSidebar()

        expect(listeners.filter((l) => l.type === 'navigate')).toHaveLength(1)
    })
})
