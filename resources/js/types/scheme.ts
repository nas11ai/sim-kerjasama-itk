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

// Sumber: App\Data\View\Scheme\SchemeEditData — kunci aturan dari SchemeRuleCatalog (W107).
export interface SchemeRules {
    min_reviewer_count: number | null
    max_reviewer_workload: number | null
    max_student_members: number | null
    allowed_output_types: string[]
}

export type SchemeIntegerRuleKey = Exclude<keyof SchemeRules, 'allowed_output_types'>

export interface SchemeEditData {
    id: number
    name: string
    code: string
    isActive: boolean
    schemeTypeId: number | null
    submissionTypeId: number | null
    maxBudget: number
    maxMembers: number
    durationMonths: number
    allowedTrlIds: number[]
    rules: SchemeRules
}

// Sumber: App\Queries\Scheme\SchemeFormOptions.
export interface SchemeFormOptions {
    schemeTypes: FilterOption[]
    submissionTypes: FilterOption[]
    trls: FilterOption[]
    outputTypes: FilterOption[]
    integerRules: FilterOption[]
}

// Sumber: Scheme\SchemeController::create/edit.
export interface SchemeFormPageProps {
    scheme: SchemeEditData | null
    options: SchemeFormOptions
}

// Isian kartu Aturan Utama; id pilihan berupa teks seperti nilai OptionSelect.
export interface SchemeMainFields {
    name: string
    code: string
    scheme_type_id: string | null
    submission_type_id: string | null
    max_budget: string
    max_members: string
    duration_months: string
}

// Isian kartu Aturan Tambahan; teks kosong dikirim sebagai null dan kuncinya dibuang (W108).
export type SchemeRuleFields = Record<SchemeIntegerRuleKey, string> & {
    allowed_output_types: string[]
}
