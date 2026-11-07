import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router, page } = vi.hoisted(() => ({
    router: { get: vi.fn(), post: vi.fn() },
    page: { props: { errors: {} as Record<string, string> } },
}))

vi.mock('@inertiajs/vue3', () => ({
    router,
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

import MonevIndexPage from '@/Pages/Monev/IndexPage.vue'
import type { MonevCycle, MonevSubmission } from '@/types/monev'

const cycle = (overrides: Partial<MonevCycle> = {}): MonevCycle => ({
    detailId: 31,
    name: 'Monev I',
    order: 3,
    needsReview: false,
    deadline: '2026-07-30T23:59:00+08:00',
    state: 'open',
    blockedReason: null,
    reportId: null,
    submittedAt: null,
    openedAt: null,
    ...overrides,
})

const submission = (overrides: Partial<MonevSubmission> = {}): MonevSubmission => ({
    id: 7,
    title: 'Smart Farming Hidroponik Kampus',
    scheme: 'Penelitian Dasar',
    status: { key: 'approved', label: 'Disetujui', variant: 'default' },
    canFill: true,
    cycles: [
        cycle(),
        cycle({
            detailId: 32,
            name: 'Monev II',
            order: 5,
            state: 'locked',
            blockedReason: 'Selesaikan Laporan Monev I lebih dulu sebelum mengisi Monev II.',
        }),
    ],
    ...overrides,
})

const route = (name: string, params?: unknown) => `/${name}/${JSON.stringify(params ?? null)}`

const mountPage = (submissions: MonevSubmission[]) =>
    mount(MonevIndexPage, { props: { submissions }, global: { mocks: { route } } })

const button = (wrapper: ReturnType<typeof mountPage>, label: string) =>
    wrapper.findAll('button').find((b) => b.text().trim() === label)

beforeEach(() => {
    vi.stubGlobal('route', route)
    page.props.errors = {}
    router.post.mockClear()
})

describe('Monev/IndexPage', () => {
    it('menggambar judul, subjudul, dan empty state frame', () => {
        const text = mountPage([]).text()

        expect(text).toContain('Daftar Monev')
        expect(text).toContain(
            'Progres monitoring & evaluasi untuk penelitian Anda yang telah disetujui.'
        )
        expect(text).toContain('Belum ada pengajuan yang disetujui')
    })

    it('menggambar kartu per induk dengan progres siklus', () => {
        const text = mountPage([submission()]).text()

        expect(text).toContain('Smart Farming Hidroponik Kampus')
        expect(text).toContain('Disetujui')
        expect(text).toContain('Pengajuan')
        expect(text).toContain('Terbuka setelah Monev I selesai')
    })

    it('[MON-CYCLE-05] siklus tanpa evaluasi yang disubmit tampil selesai dan membuka siklus berikutnya', () => {
        const wrapper = mountPage([
            submission({
                cycles: [
                    cycle({
                        state: 'done',
                        reportId: 90,
                        submittedAt: '2025-09-20T10:00:00+08:00',
                    }),
                    cycle({ detailId: 32, name: 'Monev II', order: 5, state: 'open' }),
                ],
            }),
        ])

        expect(wrapper.findAll('[data-state="done"]')[1]?.text()).toContain('Disubmit')
        expect(wrapper.find('[data-state="active"]').text()).toContain('Monev II')
        expect(button(wrapper, 'Isi Laporan Monev II')).toBeDefined()
    })

    it('[MON-CYCLE-10] induk tarikan menampilkan banner pembekuan tanpa tombol isi', () => {
        const wrapper = mountPage([
            submission({
                status: { key: 'withdrawn', label: 'Ditarik', variant: 'secondary' },
                cycles: [
                    cycle({
                        state: 'frozen',
                        blockedReason:
                            'Pengajuan ini sudah ditarik. Siklus monevnya dibekukan dan tidak menerima laporan baru.',
                    }),
                ],
            }),
        ])

        expect(wrapper.text()).toContain(
            'Pengajuan yang ditarik tidak dapat dikembalikan. Kalau penarikannya keliru, jalurnya pengajuan baru yang merujuk yang lama.'
        )
        expect(wrapper.text()).toContain('Dibekukan')
        expect(wrapper.findAll('button')).toHaveLength(0)
    })

    it('[MON-REVIEW-13] siklus terkunci tidak menawarkan aksi', () => {
        const wrapper = mountPage([
            submission({
                cycles: [
                    cycle({
                        state: 'locked',
                        blockedReason: 'Siklus monev terbuka setelah pengajuan disetujui.',
                    }),
                ],
            }),
        ])

        expect(wrapper.findAll('button')).toHaveLength(0)
        expect(wrapper.find('[data-state="locked"]').exists()).toBe(true)
    })

    it('siklus tertutup menampilkan badge Ditutup', () => {
        const wrapper = mountPage([submission({ cycles: [cycle({ state: 'closed' })] })])

        const badge = wrapper.find('[title]')
        expect(badge.text()).toBe('Monev I Ditutup')
        expect(badge.attributes('title')).toBe(
            'Batas waktu terlewat. Hubungi Operator LPPM bila membutuhkan perpanjangan.'
        )
        expect(wrapper.text()).toContain('Ditutup — deadline lewat')
    })

    it('anggota tim melihat siapa yang mengisi laporan', () => {
        const wrapper = mountPage([submission({ canFill: false })])

        expect(button(wrapper, 'Isi Laporan Monev I')).toBeUndefined()
        expect(wrapper.text()).toContain('Laporan diisi oleh ketua pengajuan.')
    })

    it('Isi Laporan memanggil monev.reports.store', async () => {
        const wrapper = mountPage([submission()])

        await button(wrapper, 'Isi Laporan Monev I')?.trigger('click')

        expect(router.post).toHaveBeenCalledWith(
            route('monev.reports.store', { submission: 7, detail: 31 }),
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('draft laporan ketua menawarkan Lanjutkan Laporan ke halaman isi', () => {
        const wrapper = mountPage([submission({ cycles: [cycle({ reportId: 90 })] })])

        expect(button(wrapper, 'Isi Laporan Monev I')).toBeUndefined()
        const link = wrapper
            .findAll('a')
            .find((a) => a.text().includes('Lanjutkan Laporan Monev I'))
        expect(link?.attributes('href')).toBe(route('monev.reports.edit', 90))
    })

    it('error gerbang tampil sebagai banner di atas daftar', () => {
        page.props.errors = {
            form_phase_detail_id: 'Selesaikan "Monev I" lebih dulu — tahapannya berurutan.',
        }

        expect(mountPage([submission()]).text()).toContain(
            'Selesaikan "Monev I" lebih dulu — tahapannya berurutan.'
        )
    })
})
