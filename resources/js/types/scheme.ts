import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'

// Sumber: App\Data\View\Scheme\SchemeRowData.
export interface SchemeRowData {
    id: number
    name: string
    code: string
    submissionType: string | null
    schemeType: string | null
    maxBudget: number
    maxMembers: number
    durationMonths: number
    activeSubmissionCount: number
    isActive: boolean
}

export type SchemeIndexFilters = {
    search: string
    type: string
    status: string
}

// Sumber: Scheme\SchemeController::index + SchemeDirectory.
export interface SchemeIndexPageProps {
    schemes: Paginated<SchemeRowData>
    filters: SchemeIndexFilters
    typeOptions: FilterOption[]
    statusOptions: FilterOption[]
}
