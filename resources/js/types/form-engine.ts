import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'

// Sumber: App\Enums\DeadlineType (BR-FE-25).
export type DeadlineTypeValue = 'submit' | 'evaluation' | 'revision'

// Sumber: App\Queries\Form\SubmissionDateOptions.
export interface SubmissionDateOption {
    id: number
    type: DeadlineTypeValue
    cycle: number | null
    label: string
}

// Sumber: App\Data\View\FormEngine\PeriodDateData.
export interface PeriodDateData {
    id: number
    type: DeadlineTypeValue
    cycle: number | null
    label: string
    at: string
    inputValue: string
    passed: boolean
    phaseDetailCount: number
}

// Sumber: App\Data\View\FormEngine\PeriodRowData.
export interface PeriodRowData {
    id: number
    name: string
    status: FilterOption
    dates: PeriodDateData[]
    formPhaseIds: number[]
    submittedCount: number
    draftOwnerCount: number
    closable: boolean
    deleteBlockers: { label: string; count: number }[]
}

export type PeriodIndexFilters = {
    search: string
    status: string
    year: string
}

// Sumber: FormEngine\SubmissionPeriodController::index + SubmissionPeriodList.
export interface PeriodIndexPageProps {
    periods: Paginated<PeriodRowData>
    filters: PeriodIndexFilters
    statusOptions: FilterOption[]
    yearOptions: FilterOption[]
    typeOptions: FilterOption[]
    phaseOptions: FilterOption[]
    can: { viewSubmissions: boolean }
}
