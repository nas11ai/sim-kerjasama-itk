import type { Paginated } from '@/types/pagination'
import type { FilterOption } from '@/types/submission-page'
import type { WizardFieldType } from '@/types/submission-wizard'

// Sumber: App\Enums\DeadlineType (BR-FE-25).
// `output` = Batas Akhir detail Upload Luaran, selalu tanpa siklus (W139).
export type DeadlineTypeValue = 'submit' | 'evaluation' | 'revision' | 'output'

// ADR 0019 — jenis tenggat yang dipegang langkah fase; tanggalnya milik kalender periode.
export type StepDeadlineType = Extract<DeadlineTypeValue, 'submit' | 'output'>

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

// Sumber: App\Data\View\FormEngine\CohortEvaluationVersionData — BR-REV-18, BR-REV-19.
// `pinId` null = angkatan belum mulai dinilai, jadi otomatis memakai `latestLabel`.
export interface CohortEvaluationVersion {
    phaseTitle: string
    stepLabel: string
    formTitle: string
    pinId: number | null
    pinnedLabel: string | null
    latestLabel: string
    responseCount: number
    decidedCount: number
    canApply: boolean
    // K-3 (b) — reviewer-nya terkunci Batas Evaluasi sampai Operator memberi perpanjangan.
    pastDeadline: PastDeadlineSubmission[]
}

// Sumber: App\Data\View\FormEngine\PastDeadlineSubmissionData.
export interface PastDeadlineSubmission {
    submissionId: number
    researcherName: string
}

// Sumber: FormEngine\CohortEvaluationVersionController::index.
export interface PeriodEvaluationVersionsPageProps {
    period: { id: number; name: string }
    rows: CohortEvaluationVersion[]
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

// Sumber: App\Queries\Form\FieldTypeOptions — `value` id tipe sebagai string; `limitType` batas W103;
// `name` = nama FieldType, dipakai Pratinjau Form Evaluasi untuk merender field seperti reviewer.
export interface FieldTypeOption {
    value: string
    name: WizardFieldType
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

// Sumber: App\Data\View\FormEngine\PhaseCardData — `submissionCount` > 0 mengunci hapus (BR-FE-20);
// `submissionFormTitle` = Form langkah Pengajuan (BR-FE-28), null bila tahap belum punya langkah itu.
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
    submissionFormTitle: string | null
}

// Sumber: FormEngine\FormPhaseController::index — pustaka fase; `filterForm` dari `?form=`.
export interface PhaseIndexPageProps {
    phases: PhaseCardData[]
    filterForm: FormHeaderData | null
    can: { linkPeriod: boolean }
}

export type FormEditPageProps = FormEditGeneralProps | FormEditAccessProps

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
    deadlineType: StepDeadlineType
    deadlineCycle: number | null
    deadlineLabel: string
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
    // BR-FE-32 — "Versi n" terbaru; null di mode buat.
    versionLabel: string | null
}

// Sumber: App\Queries\Form\PhaseAccessOptions — `value` id aturan akses sebagai string (W102).
export interface PhaseAccessOption {
    value: string
    label: string
    formId: number
    formTitle: string
}

// Sumber: App\Data\View\FormEngine\EvaluationFieldEditData — tanpa id: Terbitkan menyalin baris baru (BR-REV-18).
export interface EvaluationFieldEditData {
    label: string
    description: string | null
    fieldTypeId: number
    isRequired: boolean
    limits: EvaluationLimits
    options: { label: string; value: string | null }[]
}

export interface EvaluationLimits {
    minLength: number | null
    maxLength: number | null
    minValue: number | null
    maxValue: number | null
}

// Sumber: App\Data\View\FormEngine\EvaluationFormCardData — `latestVersionLabel` null = belum terbit (BR-FE-30).
export interface EvaluationFormCardData {
    id: number
    title: string
    latestVersionLabel: string | null
    hasDraft: boolean
    stepCount: number
    assignmentCount: number
}

// Sumber: FormEngine\EvaluationFormController::index.
export interface EvaluationFormIndexPageProps {
    forms: EvaluationFormCardData[]
}

// Sumber: App\Data\View\FormEngine\EvaluationFormEditPageData — `fields` dari draf, kalau tidak dari versi terbaru (BR-FE-31).
export interface EvaluationFormEditPageData {
    id: number
    title: string
    description: string | null
    fields: EvaluationFieldEditData[]
    hasDraft: boolean
    latestVersionLabel: string | null
    versions: { label: string; publishedAt: string; publishedBy: string | null }[]
    usedBy: { phaseId: number; phaseTitle: string; stepLabel: string }[]
    impact: { nextLabel: string; pinnedCohorts: number }
}

// Sumber: FormEngine\EvaluationFormController::create/edit — `form` null = mode buat.
export interface EvaluationFormEditPageProps {
    form: EvaluationFormEditPageData | null
    fieldTypeOptions: FieldTypeOption[]
}

// Sumber: App\Data\View\FormEngine\StepEvaluationFormData — `assignmentCount` > 0: lepas ditolak (BR-FE-22).
export interface StepEvaluationFormData {
    id: number
    formId: number
    title: string
    versionLabel: string
    isRequired: boolean
    isActive: boolean
    assignmentCount: number
}

// Sumber: App\Data\View\FormEngine\EvaluationSectionData — satu per detail ber-needs_review.
export interface EvaluationSectionData {
    detailId: number
    detailLabel: string
    attached: StepEvaluationFormData[]
    // W158 — pengajuan ber-reviewer aktif yang tak otomatis menerima form baru.
    reviewsInProgress: number
}

// Sumber: FormEngine\FormPhaseController::edit/create — mode buat: `evaluation`/`evaluationOptions` kosong.
export interface PhaseEditPageProps {
    phase: PhaseEditData
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
    evaluation: EvaluationSectionData[]
    // BR-FE-30 — Form Evaluasi terbit, urut judul; tiap bagian menyaring yang sudah terpasang.
    evaluationOptions: FilterOption[]
    // B5 — id Form tapis pustaka asal; Kembali/Batalkan/simpan kembali ke pustaka bertapis itu.
    returnForm: number | null
}
