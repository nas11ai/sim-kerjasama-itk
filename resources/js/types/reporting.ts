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

// Sumber: FollowUpItemData.
export interface FollowUpItem {
    id: number
    title: string
    leader: string
    scheme: string | null
    ageDays: number
    href: string
}

// Sumber: FollowUpListData. items [] dan href null tanpa submissions.view-all (W148).
export interface FollowUpList {
    key: 'awaitingReviewer' | 'awaitingRevision' | 'approvedWithoutOutput'
    title: string
    hint: string
    count: number
    items: FollowUpItem[]
    href: string | null
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
    followUp?: FollowUpList[]
}

// --- Export Data (OPERATOR-07-02), cermin ExportFormOptions dan ExportHistory (W154) ---

export type ExportFilterKey = 'year' | 'scheme' | 'status'

export interface ExportTypeOption {
    value: string
    label: string
    filterKeys: ExportFilterKey[]
}

// Sumber: ExportHistory. `file_is_gone` juga true selama berkas belum dibuat (antrean).
export interface ExportJobRow {
    id: number
    kind: 'export' | 'print'
    type: string
    type_value: string
    format: string
    filters: Record<string, number | string>
    parameters: string
    status: 'queued' | 'processing' | 'completed' | 'failed'
    row_count: number
    attempts: number
    failure_reason: string | null
    requested_at: string | null
    completed_at: string | null
    file_is_gone: boolean
    file_expires_at: string | null
    download_url: string | null
    link_expires_at: string | null
    submission_href: string | null
}

export interface ExportFormOptions {
    types: ExportTypeOption[]
    formats: FilterOption[]
    schemeOptions: FilterOption[]
    statusOptions: FilterOption[]
    yearOptions: FilterOption[]
}

export interface ExportPageProps extends ExportFormOptions {
    threshold: number
    retentionHours: number
    maxAttempts: number
    initialType: string | null
    jobs: ExportJobRow[]
}
