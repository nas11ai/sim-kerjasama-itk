import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { post: vi.fn(), reload: vi.fn() } }))

vi.mock('@inertiajs/vue3', () => ({
    router,
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        props: { href: { type: String, required: true } },
        setup:
            (props, { slots }) =>
            () =>
                h('a', { href: props.href }, slots.default?.()),
    }),
    useForm: <T extends object>(data: T) => ({ ...data, errors: {}, processing: false }),
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import MonevManageShowPage from '@/Pages/Monev/ManageShowPage.vue'
import type { MonevCycle, MonevManagedCycle, MonevManageShowPageProps } from '@/types/monev'

const cycle = (overrides: Partial<MonevCycle> = {}): MonevCycle => ({
    detailId: 31,
    name: 'Monev I',
    order: 3,
    needsReview: true,
    deadline: '2026-07-30T23:59:00+08:00',
    state: 'submitted',
    blockedReason: null,
    reportId: 90,
    submittedAt: '2026-07-03T10:00:00+08:00',
    openedAt: '2026-06-01T08:00:00+08:00',
    ...overrides,
})

const managed = (overrides: Partial<MonevManagedCycle> = {}): MonevManagedCycle => ({
    cycle: cycle(),
    reviewers: [
        {
            reviewerId: 5,
            name: 'Dr. Rina Wijaya',
            nidn: null,
            type: 'internal',
            replaced: false,
            evaluationLabel: 'Evaluasi berjalan',
            accountActive: true,
        },
    ],
    phaseAccess: null,
    overrideTarget: null,
    canOpen: false,
    openHint: null,
    canAssign: true,
    canExtend: false,
    ...overrides,
})

const lockedSecond = managed({
    cycle: cycle({
        detailId: 32,
        name: 'Monev II',
        order: 5,
        state: 'locked',
        reportId: null,
        submittedAt: null,
        openedAt: null,
        blockedReason: 'Selesaikan Laporan Monev I lebih dulu sebelum mengisi Monev II.',
    }),
    reviewers: [],
    canAssign: false,
    openHint: 'Aktivasi terbuka setelah siklus Monev I selesai dievaluasi.',
})

const props = (cycles: MonevManagedCycle[]): MonevManageShowPageProps => ({
    submission: {
        id: 7,
        title: 'Smart Farming Hidroponik Kampus',
        leader: 'Ir. Dewi Lestari',
        status: { key: 'approved', label: 'Disetujui', variant: 'default' },
    },
    cycles,
    suggestedReviewerIds: [5],
})

const route = (name: string, params?: unknown) => `/${name}/${JSON.stringify(params ?? null)}`

const mountPage = (cycles: MonevManagedCycle[]) =>
    mount(MonevManageShowPage, { props: props(cycles), global: { mocks: { route } } })

const buttonOf = (wrapper: ReturnType<typeof mountPage>, label: string) => {
    const found = wrapper.findAll('button').find((b) => b.text().includes(label))
    if (found === undefined) throw new Error(`tombol "${label}" tidak ada`)
    return found
}

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.post.mockClear()
})

describe('MonevManageShowPage', () => {
    it('judul, subjudul, dan banner frame', () => {
        const text = mountPage([managed(), lockedSecond]).text()

        expect(text).toContain('Aktivasi Siklus & Penugasan Penilai')
        expect(text).toContain('Smart Farming Hidroponik Kampus · Ir. Dewi Lestari')
        expect(text).toContain('Penugasan penilai monev dilakukan manual per siklus.')
        expect(text).toContain(
            'Mengaktifkan siklus memberi tahu peneliti dan penilai yang ditugaskan pada siklus itu.'
        )
        expect(text).toContain('Monev I · Siklus Aktif')
        expect(text).toContain('Dikirim')
        expect(text).toContain('Dr. Rina Wijaya')
        expect(text).toContain('Lihat Laporan Peneliti')
    })

    it('[MON-REVIEW-02] siklus kedua tanpa reviewer menampilkan banner tanpa pewarisan', () => {
        const open = managed({
            cycle: cycle({ detailId: 32, name: 'Monev II', state: 'open', reportId: null }),
            reviewers: [],
        })
        const text = mountPage([managed(), open]).text()

        expect(text).toContain(
            'Siklus Monev II belum punya penilai. Penilai siklus sebelumnya tidak ikut otomatis.'
        )
        expect(text).toContain('Belum ditugaskan')
        expect(text).toContain('Belum dimulai')
    })

    it('siklus terkunci menonaktifkan Aktifkan dengan alasan', () => {
        const wrapper = mountPage([managed(), lockedSecond])

        expect(buttonOf(wrapper, 'Aktifkan Siklus Monev II').attributes('disabled')).toBeDefined()
        expect(wrapper.text()).toContain(
            'Aktivasi terbuka setelah siklus Monev I selesai dievaluasi.'
        )
        expect(wrapper.text()).toContain('Monev II · Belum Aktif')
    })

    it('Aktifkan memanggil monev.manage.open', async () => {
        const ready = managed({
            cycle: cycle({ state: 'open', reportId: null, submittedAt: null, openedAt: null }),
            reviewers: [],
            canOpen: true,
        })
        const wrapper = mountPage([ready])

        await buttonOf(wrapper, 'Aktifkan Siklus Monev I').trigger('click')

        expect(router.post).toHaveBeenCalledWith(
            route('monev.manage.open', { submission: 7, detail: 31 }),
            {},
            expect.objectContaining({ preserveScroll: true })
        )
    })
})
