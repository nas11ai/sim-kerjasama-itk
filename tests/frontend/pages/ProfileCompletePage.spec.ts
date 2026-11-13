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
                post: (url: string, options: object) => submit(url, options),
            }),
        Head: defineComponent({ setup: () => () => null }),
        Link: defineComponent({
            props: { href: { type: String, required: true } },
            setup:
                (props, { slots }) =>
                () =>
                    h('a', { href: props.href }, slots.default?.()),
        }),
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

import CompletePage from '@/Pages/Profile/CompletePage.vue'
import type { OwnProfile, ProfileCompletePageProps } from '@/types/profile'

const profile = (overrides: Partial<OwnProfile> = {}): OwnProfile => ({
    name: 'Dr. Budi Santoso',
    email: 'budi@itk.ac.id',
    hasProfile: true,
    nidn: '0011223344',
    organization: 'Teknik Mesin',
    phone: null,
    expertise: null,
    photoUrl: null,
    missing: ['Nomor Telepon', 'Bidang Keahlian', 'Foto Profil'],
    ...overrides,
})

const route = (name: string, params?: Record<string, string>) =>
    params === undefined ? `/${name}` : `/${name}?${new URLSearchParams(params).toString()}`

const mountPage = (overrides: Partial<ProfileCompletePageProps> = {}) =>
    mount(CompletePage, {
        props: { profile: profile(), next: null, ...overrides },
        global: { mocks: { route } },
    })

beforeEach(() => {
    submit.mockClear()
    vi.stubGlobal('route', route)
})

describe('Profile/CompletePage', () => {
    it('menggambar isi frame SHARED-01-06', () => {
        const text = mountPage().text()

        expect(text).toContain('Lengkapi Profil')
        expect(text).toContain(
            'Lengkapi profil Anda terlebih dahulu untuk dapat membuat pengajuan.'
        )
        expect(text).toContain(
            'Data dasar berasal dari registrasi dan tidak dapat diubah di halaman ini.'
        )
        expect(text).toContain('Data Registrasi')
        expect(text).toContain('Data Tambahan')
        expect(text).toContain('JPG/PNG maks 2MB.')
    })

    it('[IAM-VERIFY-12] banner menyebut data tambahan yang belum terisi', () => {
        const wrapper = mountPage({
            profile: profile({
                phone: '0813-9876-5432',
                missing: ['Bidang Keahlian', 'Foto Profil'],
            }),
        })

        expect(wrapper.text()).toContain(
            '2 dari 3 data tambahan belum terisi. Profil dihitung lengkap hanya kalau ketiganya ada.'
        )
    })

    it('[Paket 2 P1] datang dari Buat Pengajuan Baru: banner menjelaskan pengalihan', () => {
        const text = mountPage({ next: 'submission' }).text()

        expect(text).toContain('Pengajuan baru menunggu profil lengkap')
        expect(text).toContain('Anda diarahkan ke sini dari Buat Pengajuan Baru.')
        expect(mountPage().text()).not.toContain('Anda diarahkan ke sini')
    })

    it('banner sebagian tidak tampil bila ketiganya kosong', () => {
        expect(mountPage().text()).not.toContain('dari 3 data tambahan belum terisi')
    })

    it('Data Registrasi read-only dan Nanti Saja ke dasbor', () => {
        const wrapper = mountPage()

        expect(wrapper.text()).toContain('0011223344')
        expect(wrapper.text()).toContain('Teknik Mesin')
        expect(wrapper.find('#profile-name').exists()).toBe(false)
        expect(wrapper.find('a[href="/dashboard"]').text()).toContain('Nanti Saja')
    })

    it('foto tersimpan membuat unggahan opsional', () => {
        const wrapper = mountPage({
            profile: profile({
                photoUrl: '/profile/photo?v=1',
                missing: ['Nomor Telepon', 'Bidang Keahlian'],
            }),
        })

        expect(wrapper.text()).toContain('Foto tersimpan')
    })

    it('Simpan Profil mengirim next dengan form data', async () => {
        await mountPage({ next: 'submission' }).find('form').trigger('submit')

        expect(submit).toHaveBeenCalledWith(
            '/profile.complete.store?next=submission',
            expect.objectContaining({ forceFormData: true })
        )
    })
})
