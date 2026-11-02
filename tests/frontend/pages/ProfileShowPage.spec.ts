import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { submit } = vi.hoisted(() => ({ submit: vi.fn() }))

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        useForm: <T extends object>(data: T) =>
            reactive({
                ...data,
                processing: false,
                errors: {},
                patch: (url: string) => submit('patch', url),
                post: (url: string) => submit('post', url),
                put: (url: string) => submit('put', url),
                reset: vi.fn(),
            }),
        Head: defineComponent({ setup: () => () => null }),
    }
})
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import ShowPage from '@/Pages/Profile/ShowPage.vue'
import type { OwnProfile } from '@/types/profile'

const profile = (overrides: Partial<OwnProfile> = {}): OwnProfile => ({
    name: 'Dr. Budi Santoso',
    email: 'budi@itk.ac.id',
    hasProfile: true,
    nidn: '0011223344',
    organization: 'Teknik Mesin',
    phone: '0812-3456-7890',
    expertise: 'Konversi Energi',
    photoUrl: null,
    missing: [],
    ...overrides,
})

const route = (name: string) => `/${name}`

const mountPage = (overrides: Partial<OwnProfile> = {}) =>
    mount(ShowPage, {
        props: { profile: profile(overrides) },
        global: { mocks: { route } },
    })

beforeEach(() => {
    submit.mockClear()
    vi.stubGlobal('route', route)
})

describe('Profile/ShowPage', () => {
    it('menggambar tiga kartu frame', () => {
        const text = mountPage().text()

        expect(text).toContain('Profil Saya')
        expect(text).toContain('Kelola data profil dan keamanan akun Anda.')
        expect(text).toContain('Foto Profil')
        expect(text).toContain('Data Profil')
        expect(text).toContain('Ganti Password')
    })

    it('email dan NIDN read-only dengan hint', () => {
        const wrapper = mountPage()

        expect(wrapper.find('#profile-email').attributes('disabled')).toBeDefined()
        expect(wrapper.find('#profile-nidn').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain('Tidak dapat diubah sendiri')
    })

    it('tanpa profil hanya nama, email, dan kata sandi', () => {
        const wrapper = mountPage({ hasProfile: false, nidn: null, phone: null, expertise: null })

        expect(wrapper.find('#profile-name').exists()).toBe(true)
        expect(wrapper.find('#profile-email').exists()).toBe(true)
        expect(wrapper.find('#profile-nidn').exists()).toBe(false)
        expect(wrapper.find('#profile-phone').exists()).toBe(false)
        expect(wrapper.find('#profile-expertise').exists()).toBe(false)
        expect(wrapper.text()).not.toContain('Ganti Foto')
        expect(wrapper.text()).toContain('Ganti Password')
    })

    it('Simpan Perubahan mengirim PATCH ke profile.update', async () => {
        const wrapper = mountPage()

        await wrapper.find('form[aria-label="Data Profil"]').trigger('submit')

        expect(submit).toHaveBeenCalledWith('patch', '/profile.update')
    })

    it('Ubah Password mengirim PUT ke password.update', async () => {
        const wrapper = mountPage()

        await wrapper.find('form[aria-label="Ganti Password"]').trigger('submit')

        expect(submit).toHaveBeenCalledWith('put', '/password.update')
    })

    it('avatar memakai photoUrl bila ada, inisial bila tidak', () => {
        expect(mountPage({ photoUrl: '/profile/photo?v=1' }).find('img').attributes('src')).toBe(
            '/profile/photo?v=1'
        )
        expect(mountPage().find('img').exists()).toBe(false)
        expect(mountPage().text()).toContain('DB')
    })
})
