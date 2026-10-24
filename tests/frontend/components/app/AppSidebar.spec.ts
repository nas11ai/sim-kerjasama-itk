import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h } from 'vue'

import AppSidebar from '@/Components/app/AppSidebar.vue'
import { SidebarProvider } from '@/Components/ui/sidebar'

const { post } = vi.hoisted(() => ({ post: vi.fn() }))

const page = {
    props: {
        auth: {
            user: {
                name: 'Budi Peneliti',
                email: 'budi@itk.ac.id',
                permissions: ['submissions.view-own', 'submissions.create'],
            },
        },
        navigation: { hasApprovedSubmission: false },
    },
}

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router: { post },
    Link: defineComponent({
        props: { href: { type: String, default: '' } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
}))

const routeStub = (name?: string) => (name ? `/${name}` : { current: () => false })

let wrapper: ReturnType<typeof mount>

const mountSidebar = () =>
    mount(defineComponent({ render: () => h(SidebarProvider, null, () => h(AppSidebar)) }), {
        global: { mocks: { route: routeStub } },
        attachTo: document.body,
    })

const button = (label: string) =>
    Array.from(document.querySelectorAll('button')).find((b) => b.textContent?.trim() === label)

beforeEach(() => vi.stubGlobal('route', routeStub))

afterEach(() => {
    wrapper.unmount()
    post.mockReset()
    vi.unstubAllGlobals()
})

describe('AppSidebar', () => {
    it('menampilkan menu sesuai permission peneliti', () => {
        wrapper = mountSidebar()

        expect(wrapper.text()).toContain('Dashboard')
        expect(wrapper.text()).toContain('Daftar Pengajuan')
        expect(wrapper.text()).not.toContain('Semua Pengajuan')
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

        expect(post).toHaveBeenCalledWith('/logout')
    })
})
