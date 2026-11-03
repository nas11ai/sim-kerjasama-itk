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

// Sumber: App\Data\View\FormEngine\FormRowData.
export interface FormRowData {
    id: number
    title: string
    typeName: string
    fieldCount: number
    activeSubmissionCount: number
    submissionCount: number
    isActive: boolean
}

export type FormIndexFilters = {
    search: string
    type: string
    status: string
}

// Sumber: FormEngine\FormController::index + FormDirectory.
export interface FormIndexPageProps {
    forms: Paginated<FormRowData>
    filters: FormIndexFilters
    typeOptions: FilterOption[]
    statusOptions: FilterOption[]
}

// Sumber: App\Queries\Form\FieldTypeOptions — `value` id tipe sebagai string.
export interface FieldTypeOption {
    value: string
    label: string
    hasOptions: boolean
}

// Sumber: App\Data\View\FormEngine\FormHeaderData.
export interface FormHeaderData {
    id: number
    title: string
    isActive: boolean
}

// Sumber: App\Data\View\FormEngine\FormFieldEditData — `answerCount` > 0 mengunci tipe (BR-FE-23).
export interface FormFieldEditData {
    id: number
    label: string
    fieldTypeId: number
    isRequired: boolean
    requiredSince: string | null
    answerCount: number
    options: { id: number; label: string }[]
}

// Sumber: App\Data\View\FormEngine\ArchivedFieldData (BR-FE-12).
export interface ArchivedFieldData {
    id: number
    label: string
    typeName: string
}

// Sumber: App\Data\View\FormEngine\FormGeneralData.
export interface FormGeneralData {
    title: string
    description: string | null
    formTypeId: number | null
    isActive: boolean
    fields: FormFieldEditData[]
    archivedFields: ArchivedFieldData[]
    affectedDrafts: number
}

// Sumber: App\Data\View\FormEngine\FormAccessRuleData — `phaseDetailCount` > 0 mengunci hapus (BR-FE-20).
export interface FormAccessRuleData {
    id: number
    permission: string
    permissionLabel: string
    organizationLabel: string
    phaseDetailCount: number
}

// Sumber: FormEngine\FormController::create/edit. Satu komponen untuk semua tab (W86).
export interface FormEditGeneralProps {
    tab: 'general'
    form: FormHeaderData | null
    general: FormGeneralData
    fieldTypeOptions: FieldTypeOption[]
    formTypeOptions: FilterOption[]
}

// Sumber: FormEngine\FormAccessController::index.
export interface FormEditAccessProps {
    tab: 'access'
    form: FormHeaderData
    rules: FormAccessRuleData[]
    permissionOptions: FilterOption[]
    organizationOptions: FilterOption[]
}

export type FormEditPageProps = FormEditGeneralProps | FormEditAccessProps
