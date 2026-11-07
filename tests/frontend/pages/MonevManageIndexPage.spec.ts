import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { router } = vi.hoisted(() => ({ router: { get: vi.fn() } }))

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
}))
vi.mock('@/Layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup:
            (_, { slots }) =>
            () =>
                h('div', slots.default?.()),
    }),
}))

import MonevManageIndexPage from '@/Pages/Monev/ManageIndexPage.vue'
import type { MonevBoardFilters, MonevBoardRow, MonevManageIndexPageProps } from '@/types/monev'

const row = (overrides: Partial<MonevBoardRow> = {}): MonevBoardRow => ({
    id: 7,
    title: 'Smart Farming Hidroponik Kampus',
    leader: 'Ir. Dewi Lestari',
    scheme: 'Penelitian Mandiri',
    currentCycle: 'Monev I',
    status: 'report_in',
    ...overrides,
})

const noFilters: MonevBoardFilters = { search: '', period: '', scheme: '' }

const props = (rows: MonevBoardRow[], filters = noFilters): MonevManageIndexPageProps => ({
    rows: {
        data: rows,
        current_page: 1,
        last_page: 1,
        per_page: 15,
        total: rows.length,
        from: rows.length > 0 ? 1 : null,
        to: rows.length,
        prev_page_url: null,
        next_page_url: null,
    },
    filters,
    periodOptions: [{ value: '3', label: 'DIPA 2026' }],
    schemeOptions: [{ value: '7', label: 'Penelitian Mandiri' }],
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (rows: MonevBoardRow[], filters = noFilters) =>
    mount(MonevManageIndexPage, { props: props(rows, filters), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.get.mockClear()
})

describe('MonevManageIndexPage', () => {
    it('judul, subjudul, dan kolom frame', () => {
        const text = mountPage([row()]).text()

        expect(text).toContain('Manajemen Siklus Monev')
        expect(text).toContain('Semua submission APPROVED beserta progres siklus monev-nya.')
        for (const column of ['Judul', 'Ketua', 'Skema', 'Siklus Berjalan', 'Status']) {
            expect(text).toContain(column)
        }
    })

    it('[MON-REVIEW-01] baris menampilkan siklus berjalan dan statusnya', () => {
        const wrapper = mountPage([
            row(),
            row({
                id: 8,
                title: 'Pemetaan Karbon Biru',
                currentCycle: 'Monev II',
                status: 'not_opened',
            }),
            row({ id: 9, title: 'Katalis Limbah Sawit', status: 'awaiting_report' }),
            row({ id: 10, title: 'Selesai Semua', currentCycle: null, status: 'completed' }),
        ])
        const rows = wrapper.findAll('tbody tr').map((r) => r.text())

        expect(rows[0]).toContain('Ir. Dewi Lestari')
        expect(rows[0]).toContain('Monev I')
        expect(rows[0]).toContain('Laporan Masuk')
        expect(rows[1]).toContain('Monev II')
        expect(rows[1]).toContain('Belum Aktif')
        expect(rows[2]).toContain('Menunggu Laporan')
        expect(rows[3]).toContain('Selesai')
        expect(rows[3]).toContain('—')
        expect(wrapper.find('tbody tr a').attributes('href')).toBe('/monev.manage.show/7')
        expect(rows[0]).toContain('Kelola')
    })

    it('kosong tanpa filter dan dengan filter', () => {
        expect(mountPage([]).text()).toContain('Belum ada pengajuan dengan siklus monev')
        expect(mountPage([], { ...noFilters, search: 'x' }).text()).toContain(
            'Tidak ada pengajuan yang cocok'
        )
    })
})
