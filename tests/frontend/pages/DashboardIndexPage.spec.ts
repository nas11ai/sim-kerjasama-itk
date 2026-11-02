import { mount } from '@vue/test-utils'
import { defineComponent, h, reactive } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { page } = vi.hoisted(() => ({
    page: {
        props: {} as Record<string, unknown>,
    },
}))

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
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

import IndexPage from '@/Pages/Dashboard/IndexPage.vue'
import type { DashboardIndexPageProps, DashboardSection } from '@/types/dashboard'

const section = (key: DashboardSection['key'], title: string): DashboardSection => ({
    key,
    title,
    cards: [{ key: 'a', label: `Kartu ${title}`, value: '2', meta: 'meta', href: null }],
})

const props = (overrides: Partial<DashboardIndexPageProps> = {}): DashboardIndexPageProps => ({
    subtitle: null,
    sections: [],
    pending: null,
    activities: [],
    deadlines: [],
    announcements: [],
    profileCompletion: null,
    ...overrides,
})

const route = (name: string) => `/${name}`

const mountPage = (overrides: Partial<DashboardIndexPageProps> = {}) =>
    mount(IndexPage, { props: props(overrides), global: { mocks: { route } } })

const share = (permissions: string[]) => {
    page.props = reactive({
        auth: { user: { name: 'Dr. Budi Santoso', permissions } },
    })
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    share([])
})

describe('Dashboard/IndexPage', () => {
    it('menyapa pengguna dengan nama dan subtitle backend', () => {
        const text = mountPage({ subtitle: 'FTI — Teknik Mesin' }).text()

        expect(text).toContain('Selamat datang, Dr. Budi Santoso!')
        expect(text).toContain('FTI — Teknik Mesin')
    })

    it('merender section sesuai sections, tanpa memeriksa peran', () => {
        const text = mountPage({
            sections: [section('researcher', 'Pengajuan Saya'), section('reviewer', 'Review')],
        }).text()

        expect(text).toContain('Pengajuan Saya')
        expect(text).toContain('Kartu Review')
        expect(text).not.toContain('Manajemen')
    })

    it('banner antrian hanya bila pending ada', () => {
        expect(mountPage().text()).not.toContain('butuh reviewer')

        const text = mountPage({ pending: { awaitingReviewer: 3, awaitingVerification: 2 } }).text()
        expect(text).toContain('3 pengajuan butuh reviewer · 2 user menunggu verifikasi NIDN')
    })

    it('[IAM-VERIFY-04] profil belum lengkap: banner dan Buat Pengajuan Baru nonaktif', () => {
        share(['submissions.create', 'submissions.view-own'])
        const wrapper = mountPage({
            profileCompletion: { missing: ['Bidang Keahlian', 'Foto Profil'] },
        })

        expect(wrapper.text()).toContain('Lengkapi profil Anda sebelum membuat pengajuan.')
        expect(wrapper.text()).toContain('2 dari 3 data tambahan belum terisi.')
        expect(wrapper.find('a[href="/profile.complete"]').exists()).toBe(true)
        expect(wrapper.find('a[href="/user.submissions.create"]').exists()).toBe(false)

        const create = wrapper
            .findAll('button')
            .find((b) => b.text().includes('Buat Pengajuan Baru'))
        expect(create?.attributes('disabled')).toBeDefined()
    })

    it('tanpa profileCompletion tidak ada banner profil', () => {
        share(['submissions.create', 'submissions.view-own'])
        const wrapper = mountPage()

        expect(wrapper.text()).not.toContain('Lengkapi profil Anda')
        expect(wrapper.find('a[href="/user.submissions.create"]').exists()).toBe(true)
    })

    it('EmptyState untuk aktivitas, deadline, dan pengumuman kosong', () => {
        const text = mountPage().text()

        expect(text).toContain('Belum ada aktivitas')
        expect(text).toContain('Tidak ada deadline mendekat')
        expect(text).toContain('Belum ada pengumuman untuk Anda.')
    })

    it('aksi cepat dibaca dari permissions', () => {
        share(['submissions.create'])
        const text = mountPage().text()

        expect(text).toContain('Aksi Cepat')
        expect(text).toContain('Buat Pengajuan Baru')
        expect(text).not.toContain('Semua Pengajuan')
    })
})
