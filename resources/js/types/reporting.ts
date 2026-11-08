// Props Dashboard Statistik (OPERATOR-07-01), cermin StatisticsController dan Data PHP di
// app/Data/View/Reporting. Semua angka, persen, dan tautan dikirim backend.
import type { KpiCard } from '@/types/dashboard'
import type { FilterOption } from '@/types/submission-page'

export type StatisticsTab = 'overview' | 'now' | 'history' | 'follow-up'

export type StatisticsFilterKey = 'year' | 'period' | 'scheme'

export interface StatisticsTabLink {
    value: StatisticsTab
    label: string
    href: string
}

// Sumber: StatisticsFilters. year null = semua tahun.
export interface StatisticsFilters {
    year: number | null
    period: number | null
    scheme: number | null
}

// Sumber: StatusShareData. `share` 0–1; href null tanpa submissions.view-all.
export interface StatusShare {
    key: string
    label: string
    variant: string
    count: number
    share: number
    href: string | null
}

// Sumber: RankedCountData.
export interface RankedCount {
    label: string
    count: number
    href: string | null
}

// Sumber: SchemeYearData. `counts` dikunci tahun.
export interface SchemeYearChart {
    years: number[]
    rows: { scheme: string; counts: Record<string, number> }[]
}

// Sumber: StatisticsOverview (tab Ringkasan).
export interface StatisticsOverview {
    kpis: KpiCard[]
    byStatus: StatusShare[]
    bySchemeYear: SchemeYearChart
    byFaculty: RankedCount[]
    byStudyProgram: RankedCount[]
    byOutputType: RankedCount[]
}

// Sumber: StageBacklogData. oldestAgeDays null bila tahap kosong.
export interface StageBacklog {
    key: string
    label: string
    count: number
    oldestAgeDays: number | null
    href: string | null
}

// Sumber: ReviewerWorkloadData.
export interface ReviewerWorkload {
    rows: RankedCount[]
    idleCount: number
}

// Tab Saat Ini (W152): StageBacklog, MonevStatusCounts, ReviewerCoverage, ReviewerWorkload.
export interface StatisticsNow {
    backlog: StageBacklog[]
    monev: RankedCount[]
    coverage: RankedCount[]
    workload: ReviewerWorkload
}

// Sumber: TrendData. unit 'month' = 12 titik Jan..Des; 'year' = satu titik per tahun.
export interface Trend {
    unit: 'month' | 'year'
    points: { label: string; count: number }[]
}

// Sumber: SchemePerformanceData. href null tanpa submissions.view-all atau tanpa skema.
export interface SchemePerformance {
    scheme: string
    submitted: number
    approved: number
    rejected: number
    withdrawn: number
    approvalRate: string
    approvedBudget: string
    href: string | null
}

// Tab Riwayat (W152): MonthlyTrend + SchemePerformance.
export interface StatisticsHistory {
    submitted: Trend
    outputs: Trend
    schemes: SchemePerformance[]
}

export interface StatsPageProps {
    tab: StatisticsTab
    tabs: StatisticsTabLink[]
    filters: StatisticsFilters
    filterKeys: StatisticsFilterKey[]
    yearOptions: FilterOption[]
    periodOptions: FilterOption[]
    schemeOptions: FilterOption[]
    can: { viewSubmissions: boolean }
    // Hanya tab aktif yang dikirim (W156).
    overview?: StatisticsOverview
    now?: StatisticsNow
    history?: StatisticsHistory
}
