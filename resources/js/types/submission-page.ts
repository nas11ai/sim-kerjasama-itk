// Kontrak props halaman Submission/*, mengikuti app/Data/View/Submission dan
// app/Queries/Submission/Researcher*.
import type { Paginated } from '@/types/pagination'
import type { SubmissionStatusView } from '@/types/submission-status'

export interface FilterOption {
    value: string
    label: string
}

// Sumber: SubmissionRowData.
export interface SubmissionRow {
    id: number
    title: string
    scheme: string | null
    period: string | null
    status: SubmissionStatusView
    submittedAt: string | null
    isLead: boolean
    isArchived: boolean
}

export type SubmissionIndexFilters = {
    search: string
    status: string
    period: string
}

export interface SubmissionIndexPageProps {
    submissions: Paginated<SubmissionRow>
    filters: SubmissionIndexFilters
    statusOptions: FilterOption[]
    periodOptions: FilterOption[]
}
