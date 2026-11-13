import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { post, reload, poll, toast } = vi.hoisted(() => ({
    post: vi.fn(),
    toast: vi.fn(),
    reload: vi.fn(),
    poll: { start: vi.fn(), stop: vi.fn() },
}))

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ setup: () => () => null }),
    Link: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('a', slots.default?.()),
    }),
    router: { post, reload },
    usePoll: () => poll,
}))
vi.mock('@/Components/ui/toast', () => ({ toast }))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))
vi.mock('@/Components/Reporting/ExportRequestCard.vue', () => ({
    default: defineComponent({ setup: () => () => h('div', 'request-card') }),
}))

import ExportPage from '@/Pages/Reporting/ExportPage.vue'
import type { ExportJobRow, ExportPageProps } from '@/types/reporting'

import { exportJob } from '../../fixtures/exportJob'

const route = (name: string) =>
    name === 'reporting.exports.store' ? '/reporting/export' : `/${name}`
vi.stubGlobal('route', route)

const mountPage = (jobs: ExportJobRow[]) => {
    const props: ExportPageProps = {
        options: { types: [], formats: [], schemeOptions: [], statusOptions: [], yearOptions: [] },
        threshold: 100,
        retentionHours: 24,
        maxAttempts: 3,
        initialType: null,
        jobs,
    }
    return mount(ExportPage, { props, global: { mocks: { route } } })
}
const button = (wrapper: ReturnType<typeof mountPage>, label: string) =>
    wrapper.findAll('button').find((b) => b.text().includes(label))

beforeEach(() => {
    vi.clearAllMocks()
    vi.setSystemTime(new Date('2026-07-03T09:00:00+08:00'))
})

describe('ExportPage', () => {
    it('judul, subjudul, kartu, dan catatan kaki frame', () => {
        const text = mountPage([]).text()

        expect(text).toContain('Ekspor Data')
        expect(text).toContain(
            'Ekspor diproses di latar belakang: Anda diberi tahu saat berkas siap diunduh.'
        )
        expect(text).toContain('Riwayat Ekspor')
        expect(text).toContain(
            'Tautan unduhan berlaku 24 jam sejak berkas siap. Ekspor yang gagal dapat dijalankan ulang lewat tombol "Coba Lagi".'
        )
    })

    it('Coba Lagi mengirim ulang jenis, format, dan filter baris itu', async () => {
        const job = exportJob({
            status: 'failed',
            format: 'csv',
            filters: { year_from: 2025, status: 'approved' },
        })

        await button(mountPage([job]), 'Coba Lagi')?.trigger('click')

        expect(post).toHaveBeenCalledWith(
            '/reporting/export',
            { type: 'submissions', format: 'csv', year_from: 2025, status: 'approved' },
            expect.objectContaining({ preserveScroll: true })
        )
    })

    it('[E2E-02] Coba Lagi yang ditolak validasi memberi tahu lewat toast', async () => {
        post.mockImplementation(
            (
                _url: string,
                _data: unknown,
                options: { onError: (e: Record<string, string>) => void }
            ) => options.onError({ scheme: 'Skema yang dipilih tidak valid.' })
        )

        await button(
            mountPage([exportJob({ status: 'failed', download_url: null })]),
            'Coba Lagi'
        )?.trigger('click')

        expect(toast).toHaveBeenCalledWith({
            title: 'Ekspor tidak dapat dijalankan ulang',
            description: 'Skema yang dipilih tidak valid.',
            variant: 'destructive',
        })
    })

    it('Minta Tautan Baru memuat ulang riwayat saja', async () => {
        await button(
            mountPage([exportJob({ link_expires_at: '2026-07-03T08:00:00+08:00' })]),
            'Minta Tautan Baru'
        )?.trigger('click')

        expect(reload).toHaveBeenCalledWith({ only: ['jobs'] })
    })

    it('polling hanya berjalan selama ada ekspor di antrean atau diproses', async () => {
        const wrapper = mountPage([
            exportJob({ status: 'processing', file_is_gone: true, download_url: null }),
        ])

        expect(poll.start).toHaveBeenCalledTimes(1)

        await wrapper.setProps({ jobs: [exportJob()] })

        expect(poll.stop).toHaveBeenCalled()
    })

    it('tanpa ekspor berjalan polling tidak dimulai', () => {
        mountPage([exportJob()])

        expect(poll.start).not.toHaveBeenCalled()
    })
})
