import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'

// Sumber: App\Enums\DeadlineType (BR-FE-25).
// `output` = Batas Akhir detail Upload Luaran, selalu tanpa siklus (W139).
export type DeadlineTypeValue = 'submit' | 'evaluation' | 'revision' | 'output'

// Sumber: App\Queries\Form\SubmissionDateOptions — `periodId`/`cycle` menyaring Batas Evaluasi/Revisi (W102).
export interface SubmissionDateOption {
    id: number
    type: DeadlineTypeValue
    cycle: number | null
    periodId: number
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

// Sumber: App\Queries\Form\FieldTypeOptions — `value` id tipe sebagai string; `limitType` batas W103.
export interface FieldTypeOption {
    value: string
    label: string
    hasOptions: boolean
    limitType: 'length' | 'value' | null
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

// Sumber: App\Data\View\FormEngine\PhaseCardData — `submissionCount` > 0 mengunci hapus (BR-FE-20).
export interface PhaseCardData {
    id: number
    title: string
    description: string | null
    isActive: boolean
    detailCount: number
    periodNames: string[]
    submissionCount: number
    linkablePeriods: FilterOption[]
    deadlineGaps: { period: string; count: number }[]
}

// Sumber: FormEngine\FormPhaseController::index.
export interface FormEditPhasesProps {
    tab: 'phases'
    form: FormHeaderData
    phases: PhaseCardData[]
    can: { linkPeriod: boolean }
}

export type FormEditPageProps = FormEditGeneralProps | FormEditAccessProps | FormEditPhasesProps

// Sumber: App\Data\View\FormEngine\PhaseDetailEditData — hitungan mengunci hapus/form/tipe/review.
export interface PhaseDetailEditData {
    id: number
    order: number
    formAccessControlId: number
    formTitle: string
    accessLabel: string
    phaseTypeId: number
    phaseTypeName: string
    needsReview: boolean
    submissionDateId: number
    evaluationDateId: number | null
    revisionDateId: number | null
    currentDeadlines: SubmissionDateOption[]
    submissionCount: number
    assignmentCount: number
    evaluationFormCount: number
}

// Sumber: App\Data\View\FormEngine\PhaseEditData — `id` null = mode buat (W94).
export interface PhaseEditData {
    id: number | null
    title: string
    description: string | null
    isActive: boolean
    details: PhaseDetailEditData[]
}

// Sumber: App\Queries\Form\PhaseAccessOptions — `value` id aturan akses sebagai string (W102).
export interface PhaseAccessOption {
    value: string
    label: string
    formId: number
    formTitle: string
}

// Sumber: App\Data\View\FormEngine\EvaluationFieldEditData — `answerCount` > 0 mengunci tipe dan hapus (W103).
export interface EvaluationFieldEditData {
    id: number
    label: string
    description: string | null
    fieldTypeId: number
    isRequired: boolean
    limits: EvaluationLimits
    answerCount: number
    options: { id: number; label: string; value: string | null }[]
}

export interface EvaluationLimits {
    minLength: number | null
    maxLength: number | null
    minValue: number | null
    maxValue: number | null
}

// Sumber: App\Data\View\FormEngine\EvaluationFormEditData — `assignmentCount` menentukan hapus (BR-FE-22).
export interface EvaluationFormEditData {
    id: number
    title: string
    description: string | null
    isRequired: boolean
    isActive: boolean
    assignmentCount: number
    fields: EvaluationFieldEditData[]
}

// Sumber: App\Data\View\FormEngine\EvaluationSectionData — satu per detail ber-needs_review.
export interface EvaluationSectionData {
    detailId: number
    detailLabel: string
    forms: EvaluationFormEditData[]
    // W158 — pengajuan ber-reviewer aktif yang tetap memakai set form saat penugasannya.
    reviewsInProgress: number
}

// Sumber: FormEngine\FormPhaseController::edit/create — mode buat: `evaluation`/`fieldTypeOptions` kosong.
export interface PhaseEditPageProps {
    form: FormHeaderData
    phase: PhaseEditData
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
    deadlineOptions: SubmissionDateOption[]
    evaluation: EvaluationSectionData[]
    fieldTypeOptions: FieldTypeOption[]
}
