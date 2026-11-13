import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'

const { useFilters } = vi.hoisted(() => ({
    useFilters: vi.fn((_: string, initial: Record<string, string>) => ({
        filters: initial,
    })),
}))

vi.mock('@/composables/useFilters', () => ({ useFilters }))
vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn() },
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

import HistoryPage from '@/Pages/Review/HistoryPage.vue'
import type { HistoryPageProps, ReviewHistoryRow } from '@/types/review'

const row = (overrides: Partial<ReviewHistoryRow>): ReviewHistoryRow => ({
    id: 1,
    title: 'Smart Farming Hidroponik',
    scheme: 'Penelitian Mandiri',
    context: 'Proposal · Periode 2025',
    completedAt: '2025-12-18T10:00:00+08:00',
    status: 'completed',
    statusLabel: 'Selesai',
    summaryUrl: '/reviewer/assignments/9/summary',
    ...overrides,
})

const props = (rows: ReviewHistoryRow[]): HistoryPageProps => ({
    history: {
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
    filters: { search: '', year: '' },
    yearOptions: [{ value: '2025', label: '2025' }],
})

const route = (name: string) => `/${name}`

const mountPage = (rows: ReviewHistoryRow[]) =>
    mount(HistoryPage, { props: props(rows), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    useFilters.mockClear()
})

describe('HistoryPage', () => {
    it('baris digantikan tanpa tautan', () => {
        const wrapper = mountPage([
            row({}),
            row({ id: 2, status: 'replaced', statusLabel: 'Digantikan', summaryUrl: null }),
        ])

        expect(wrapper.text()).toContain('Digantikan')
        expect(wrapper.findAll('a[href="/reviewer/assignments/9/summary"]')).toHaveLength(1)
        expect(wrapper.findAll('a')).toHaveLength(1)
    })

    it('pencarian memakai useFilters', () => {
        mountPage([])

        expect(useFilters).toHaveBeenCalledWith('reviewer.history.index', {
            search: '',
            year: '',
        })
    })

    it('tanpa riwayat menampilkan keadaan kosong', () => {
        expect(mountPage([]).text()).toContain('Belum ada riwayat penilaian')
    })
})
