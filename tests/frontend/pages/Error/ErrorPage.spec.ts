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

// Factory vi.mock diangkat ke atas impor, jadi `vue` diimpor di dalamnya.
vi.mock('@/Layouts/AppLayout.vue', async () => {
    const { defineComponent, h } = await import('vue')
    return {
        default: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('div', { 'data-layout': 'app' }, slots.default?.()),
        }),
    }
})
vi.mock('@/Layouts/AuthLayout.vue', async () => {
    const { defineComponent, h } = await import('vue')
    return {
        default: defineComponent({
            setup:
                (_, { slots }) =>
                () =>
                    h('div', { 'data-layout': 'auth' }, slots.default?.()),
        }),
    }
})

import ErrorPage from '@/Pages/Error/ErrorPage.vue'
import type { Auth } from '@/types'
import type { ErrorPageProps } from '@/types/error'

const route = (name: string) => `/${name}`

const signedIn: Auth = {
    user: { id: 7, name: 'Sari', email: 'sari@itk.ac.id', permissions: [] },
}

const mountPage = (props: ErrorPageProps) =>
    mount(ErrorPage, { props, global: { mocks: { route } } })

beforeEach(() => vi.stubGlobal('route', route))

describe('Error/ErrorPage', () => {
    it('user masuk: shell aplikasi dan tombol kembali ke dasbor', () => {
        const wrapper = mountPage({ status: 404, message: null, auth: signedIn })

        expect(wrapper.find('[data-layout="app"]').exists()).toBe(true)
        expect(wrapper.get('a').attributes('href')).toBe('/dashboard')
        expect(wrapper.get('a').text()).toBe('Kembali ke Dasbor')
    })

    it('tamu: layout auth dan tombol masuk', () => {
        const wrapper = mountPage({ status: 404, message: null, auth: { user: null } })

        expect(wrapper.find('[data-layout="auth"]').exists()).toBe(true)
        expect(wrapper.get('a').attributes('href')).toBe('/login')
        expect(wrapper.get('a').text()).toBe('Masuk')
    })

    it('props bersama tidak ada (URL dirender sebelum middleware web): diperlakukan sebagai tamu', () => {
        const wrapper = mountPage({ status: 500, message: null })

        expect(wrapper.find('[data-layout="auth"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Terjadi kesalahan')
    })

    it('403 menampilkan pesan penolakan bila ada', () => {
        const wrapper = mountPage({
            status: 403,
            message: 'Anda tidak memiliki akses ke formulir ini.',
            auth: signedIn,
        })

        expect(wrapper.text()).toContain('Akses ditolak')
        expect(wrapper.text()).toContain('Anda tidak memiliki akses ke formulir ini.')
        expect(wrapper.text()).not.toContain('Anda tidak memiliki akses ke halaman ini.')
    })

    it('403 tanpa pesan memakai copy bawaan halaman', () => {
        const wrapper = mountPage({ status: 403, message: null, auth: signedIn })

        expect(wrapper.text()).toContain('Anda tidak memiliki akses ke halaman ini.')
    })

    it.each([
        [404, 'Halaman tidak ditemukan', 'Alamat yang Anda buka tidak ada atau sudah dipindahkan.'],
        [500, 'Terjadi kesalahan', 'Coba lagi beberapa saat lagi.'],
        [503, 'Sedang dalam pemeliharaan', 'Sistem akan kembali sebentar lagi.'],
    ] as const)('copy status %i', (status, title, description) => {
        const text = mountPage({ status, message: null, auth: signedIn }).text()

        expect(text).toContain(title)
        expect(text).toContain(description)
    })
})
