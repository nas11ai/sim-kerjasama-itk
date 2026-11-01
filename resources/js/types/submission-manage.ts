// Props layar pengajuan sisi Operator (OPERATOR-02-*), cermin Data PHP di
// app/Data/View/Submission/Manage.
import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'

export interface OperatorSubmissionRow {
    id: number
    title: string
    leadName: string
    organization: string | null
    scheme: string | null
    status: SubmissionStatusView
    submittedAt: string | null
    reviewerCount: number
}

export type ManageIndexFilters = {
    search: string
    status: string
    period: string
    scheme: string
}

export interface ManageIndexPageProps {
    submissions: Paginated<OperatorSubmissionRow>
    filters: ManageIndexFilters
    statusOptions: FilterOption[]
    periodOptions: FilterOption[]
    schemeOptions: FilterOption[]
}
