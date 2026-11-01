import { mount } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
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

import FilterSelect from '@/Components/app/FilterSelect.vue'
import ManageIndexPage from '@/Pages/Submission/ManageIndexPage.vue'
import type {
    ManageIndexFilters,
    ManageIndexPageProps,
    OperatorSubmissionRow,
} from '@/types/submission-manage'

const row: OperatorSubmissionRow = {
    id: 42,
    title: 'Analisis Potensi Biochar di Kalimantan Timur',
    leadName: 'Dr. Budi Santoso',
    organization: 'FTI — Teknik Mesin',
    scheme: 'Penelitian DIPA 2026',
    status: { key: 'under_review', label: 'Direview', variant: 'default' },
    submittedAt: '2026-01-12T08:00:00+08:00',
    reviewerCount: 2,
}

const noFilters: ManageIndexFilters = { search: '', status: '', period: '', scheme: '' }

const props = (rows: OperatorSubmissionRow[], filters = noFilters): ManageIndexPageProps => ({
    submissions: {
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
    statusOptions: [{ value: 'approved', label: 'Disetujui' }],
    periodOptions: [{ value: '3', label: 'DIPA 2026' }],
    schemeOptions: [{ value: '7', label: 'Penelitian DIPA 2026' }],
})

const route = (name: string, param?: number) =>
    param === undefined ? `/${name}` : `/${name}/${param}`

const mountPage = (rows: OperatorSubmissionRow[], filters = noFilters) =>
    mount(ManageIndexPage, { props: props(rows, filters), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.get.mockClear()
})

describe('ManageIndexPage', () => {
    it('[SM-ACCESS-04] baris menaut ke detail operator', () => {
        const wrapper = mountPage([row])

        expect(wrapper.find('a[href="/admin.submissions.show/42"]').exists()).toBe(true)
        expect(wrapper.text()).toContain('Dr. Budi Santoso')
        expect(wrapper.text()).toContain('FTI — Teknik Mesin')
    })

    it('filter skema meminta ulang ke server', async () => {
        const wrapper = mountPage([row])
        const scheme = wrapper
            .findAllComponents(FilterSelect)
            .find((select) => select.props('label') === 'Skema')

        scheme?.vm.$emit('update:modelValue', '7')
        await nextTick()

        expect(router.get).toHaveBeenCalledWith(
            '/admin.submissions.index',
            { scheme: '7' },
            expect.objectContaining({ preserveState: true })
        )
    })

    it('tanpa hasil saat tersaring menampilkan pesan filter', () => {
        const wrapper = mountPage([], { ...noFilters, status: 'approved' })

        expect(wrapper.text()).toContain('Tidak ada pengajuan yang cocok dengan filter.')
    })

    it('tanpa pengajuan sama sekali menampilkan pesan kosong', () => {
        expect(mountPage([]).text()).toContain('Belum ada pengajuan terkirim.')
    })
})
