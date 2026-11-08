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
vi.mock('@/Components/ui/chart-bar', () => ({
    BarChart: defineComponent({
        props: { data: { type: Array, required: true } },
        setup: (props) => () => h('div', { 'data-testid': 'bar-chart' }, String(props.data.length)),
    }),
}))

import FilterSelect from '@/Components/app/FilterSelect.vue'
import OptionSelect from '@/Components/app/OptionSelect.vue'
import StatsPage from '@/Pages/Reporting/StatsPage.vue'
import type {
    StatisticsHistory,
    StatisticsNow,
    StatisticsOverview,
    StatisticsTab,
    StatsPageProps,
} from '@/types/reporting'

const overview: StatisticsOverview = {
    kpis: [
        {
            key: 'submissions',
            label: 'Total Submission',
            value: '86',
            meta: 'Tahun 2026',
            href: '/admin/submissions?year=2026',
        },
        {
            key: 'approvedBudget',
            label: 'Total Anggaran Disetujui',
            value: 'Rp 6.500.000',
            meta: null,
            href: null,
        },
        { key: 'outputs', label: 'Luaran Terkumpul', value: '38', meta: 'Semua tipe', href: null },
        {
            key: 'activeReviewers',
            label: 'Reviewer Aktif',
            value: '24',
            meta: 'Internal 20 · Eksternal 4',
            href: null,
        },
        { key: 'approvalRate', label: 'Tingkat Persetujuan', value: '64%', meta: null, href: null },
    ],
    byStatus: [
        { key: 'approved', label: 'Disetujui', variant: 'default', count: 7, share: 1, href: '/a' },
    ],
    bySchemeYear: {
        years: [2025, 2026],
        rows: [{ scheme: 'DIPA', counts: { '2025': 3, '2026': 5 } }],
    },
    byFaculty: [{ label: 'FTI', count: 4, href: null }],
    byStudyProgram: [{ label: 'Teknik Mesin', count: 4, href: null }],
    byOutputType: [],
}

const now: StatisticsNow = {
    backlog: [
        { key: 'submitted', label: 'Menunggu Review', count: 3, oldestAgeDays: 12, href: '/s' },
    ],
    monev: [{ label: 'Belum Aktif', count: 2, href: '/monev' }],
    coverage: [{ label: 'Tanpa reviewer', count: 1, href: null }],
    workload: { rows: [{ label: 'Dr. Sari (Internal)', count: 3, href: null }], idleCount: 2 },
}

const history: StatisticsHistory = {
    submitted: { unit: 'month', points: [{ label: 'Jan', count: 2 }] },
    outputs: { unit: 'month', points: [{ label: 'Jan', count: 0 }] },
    schemes: [
        {
            scheme: 'DIPA',
            submitted: 2,
            approved: 1,
            rejected: 0,
            withdrawn: 0,
            approvalRate: '100%',
            approvedBudget: 'Rp 0',
            href: '/scheme',
        },
    ],
}

const props = (tab: StatisticsTab = 'overview'): StatsPageProps => ({
    tab,
    tabs: [
        { value: 'overview', label: 'Ringkasan', href: '/stats' },
        { value: 'now', label: 'Saat Ini', href: '/stats/now' },
        { value: 'history', label: 'Riwayat', href: '/stats/history' },
        { value: 'follow-up', label: 'Tindak Lanjut', href: '/stats/follow-up' },
    ],
    filters: { year: 2026, period: null, scheme: null },
    filterKeys: tab === 'overview' ? ['year', 'period', 'scheme'] : ['period', 'scheme'],
    yearOptions: [
        { value: 'all', label: 'Semua tahun' },
        { value: '2026', label: '2026' },
    ],
    periodOptions: [{ value: '3', label: 'DIPA 2026' }],
    schemeOptions: [{ value: '7', label: 'Penelitian DIPA' }],
    can: { viewSubmissions: true },
    overview: tab === 'overview' ? overview : undefined,
    now: tab === 'now' ? now : undefined,
    history: tab === 'history' ? history : undefined,
})

const route = (name: string, params?: { tab?: string }) =>
    `/${name}${params?.tab ? `/${params.tab}` : ''}`

const mountPage = (tab: StatisticsTab = 'overview') =>
    mount(StatsPage, { props: props(tab), global: { mocks: { route } } })

beforeEach(() => {
    vi.stubGlobal('route', route)
    router.get.mockClear()
})

describe('StatsPage', () => {
    it('judul, subjudul, dan keempat tab dari frame', () => {
        const wrapper = mountPage()

        expect(wrapper.text()).toContain('Dashboard Statistik')
        expect(wrapper.text()).toContain(
            'Statistik ditampilkan langsung dari data terkini dan bersifat hanya-baca.'
        )
        for (const href of ['/stats', '/stats/now', '/stats/history', '/stats/follow-up']) {
            expect(wrapper.find(`nav a[href="${href}"]`).exists()).toBe(true)
        }
        expect(wrapper.find('nav a[href="/stats"]').attributes('aria-current')).toBe('page')
    })

    it('tab Ringkasan menampilkan lima KPI dan keempat panel frame', () => {
        const wrapper = mountPage()

        for (const label of [
            'Total Submission',
            'Total Anggaran Disetujui',
            'Luaran Terkumpul',
            'Reviewer Aktif',
            'Tingkat Persetujuan',
        ]) {
            expect(wrapper.text()).toContain(label)
        }
        for (const title of [
            'Submission per Status',
            'Submission per Skema per Tahun',
            'Submission per Fakultas / Prodi',
            'Luaran per Tipe',
        ]) {
            expect(wrapper.text()).toContain(title)
        }
        expect(wrapper.find('a[href="/admin/submissions?year=2026"]').text()).toContain('86')
    })

    it('tab Saat Ini menampilkan keempat panel W152', () => {
        const wrapper = mountPage('now')

        for (const title of [
            'Pengajuan Berjalan',
            'Siklus Monev',
            'Cakupan Reviewer',
            'Beban Reviewer',
        ]) {
            expect(wrapper.text()).toContain(title)
        }
        expect(wrapper.find('a[href="/s"]').text()).toContain('terlama 12 hari')
        expect(wrapper.text()).toContain('2 reviewer aktif belum punya tugas evaluasi.')
    })

    it('tab Riwayat menampilkan dua tren dan kinerja skema', () => {
        const wrapper = mountPage('history')

        expect(wrapper.text()).toContain('Pengajuan Disubmit per Bulan')
        expect(wrapper.text()).toContain('Luaran Terdaftar per Bulan')
        expect(wrapper.findAll('[data-testid="bar-chart"]')).toHaveLength(1)
        expect(wrapper.find('a[href="/scheme"]').text()).toBe('DIPA')
    })

    it('filter hanya yang ada di filterKeys', () => {
        expect(mountPage().findAllComponents(OptionSelect)).toHaveLength(1)
        const now = mountPage('now')

        expect(now.findAllComponents(OptionSelect)).toHaveLength(0)
        expect(now.findAllComponents(FilterSelect).map((s) => s.props('label'))).toEqual([
            'Periode',
            'Skema',
        ])
    })

    it('mengubah filter memuat ulang tab yang sama dengan tahun yang dipilih', async () => {
        const wrapper = mountPage('overview')
        wrapper
            .findAllComponents(FilterSelect)
            .find((s) => s.props('label') === 'Skema')
            ?.vm.$emit('update:modelValue', '7')
        await nextTick()

        expect(router.get).toHaveBeenCalledWith(
            '/reporting.stats',
            { year: '2026', scheme: '7' },
            expect.objectContaining({ preserveState: true })
        )
    })

    it('Semua tahun dikirim sebagai year=all', async () => {
        const wrapper = mountPage('overview')
        wrapper.findComponent(OptionSelect).vm.$emit('update:modelValue', 'all')
        await nextTick()

        expect(router.get).toHaveBeenCalledWith(
            '/reporting.stats',
            { year: 'all' },
            expect.anything()
        )
    })
})
