import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

vi.mock('@inertiajs/vue3', async () => {
    const { reactive } = await import('vue')
    return {
        router: { post: vi.fn() },
        usePage: () => ({ props: { errors: {} } }),
        useForm: <T extends object>(data: T) =>
            reactive({ ...data, processing: false, errors: {}, post: vi.fn(), reset: vi.fn() }),
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

import MonevReportShowPage from '@/Pages/Monev/ReportShowPage.vue'
import type { MonevReportShowPageProps } from '@/types/monev'

const props = (overrides: Partial<MonevReportShowPageProps> = {}): MonevReportShowPageProps => ({
    report: {
        id: 9,
        parentId: 7,
        title: 'Pemetaan Karbon Biru Pesisir Balikpapan',
        scheme: 'Pengabdian DIPA 2025',
        cycleName: 'Monev I',
        deadline: '2025-09-30T23:59:00+08:00',
        lastUpdatedAt: '2025-09-20T10:00:00.000000Z',
    },
    cycle: {
        detailId: 31,
        name: 'Monev I',
        order: 3,
        needsReview: true,
        deadline: '2025-09-30T23:59:00+08:00',
        state: 'submitted',
        blockedReason: null,
        reportId: 9,
        submittedAt: '2025-09-20T10:00:00+08:00',
        openedAt: null,
    },
    status: { key: 'submitted', label: 'Disubmit', variant: 'secondary' },
    answers: [
        {
            label: 'Ringkasan Kemajuan',
            value: 'Survey lapangan 3 lokasi pesisir selesai',
            isFile: false,
            downloadUrl: null,
        },
    ],
    scores: {
        visible: false,
        reason: 'Hasil evaluasi akan tampil begitu reviewer menyelesaikan penilaiannya.',
        scores: [],
    },
    threads: [],
    parentWithdrawn: false,
    can: { fill: false, reply: true, resolveThreads: false },
    ...overrides,
})

const route = (name: string, params?: unknown) => `/${name}/${JSON.stringify(params ?? null)}`

const mountPage = (overrides: Partial<MonevReportShowPageProps> = {}) =>
    mount(MonevReportShowPage, { props: props(overrides), global: { mocks: { route } } })

beforeEach(() => vi.stubGlobal('route', route))

describe('Monev/ReportShowPage', () => {
    it('menggambar header dan kartu frame', () => {
        const text = mountPage().text()

        expect(text).toContain('Detail Laporan Monev I')
        expect(text).toContain('Pemetaan Karbon Biru Pesisir Balikpapan · Pengabdian DIPA 2025')
        expect(text).toContain('Disubmit')
        expect(text).toContain('Laporan (Read-only)')
        expect(text).toContain('Survey lapangan 3 lokasi pesisir selesai')
        expect(text).toContain('Diskusi')
        expect(text).toContain('Belum ada diskusi.')
    })

    it('[MON-REVIEW-08] evaluasi belum disubmit menampilkan catatan menunggu', () => {
        expect(mountPage().text()).toContain(
            'Hasil evaluasi akan tampil begitu reviewer menyelesaikan penilaiannya.'
        )
    })

    it('[MON-CYCLE-11] induk tarikan menampilkan banner arsip', () => {
        expect(mountPage({ parentWithdrawn: true }).text()).toContain(
            'Pengajuan ini sudah ditarik. Laporan yang sudah dikirim tetap tersimpan sebagai arsip.'
        )
    })

    it('draft milik ketua menawarkan Lanjutkan Laporan', () => {
        const wrapper = mountPage({ can: { fill: true, reply: true, resolveThreads: false } })

        expect(wrapper.text()).toContain('Laporan ini masih draft.')
        const link = wrapper.findAll('a').find((a) => a.text().includes('Lanjutkan Laporan'))
        expect(link?.attributes('href')).toBe(route('monev.reports.edit', 9))
    })

    it('laporan terkirim tidak menawarkan Lanjutkan Laporan', () => {
        expect(mountPage().text()).not.toContain('Lanjutkan Laporan')
    })
})
