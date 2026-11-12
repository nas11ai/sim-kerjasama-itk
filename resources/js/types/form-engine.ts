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
    // BR-FE-36 — angkatan `blocked` + `needs_fix` periode ini.
    unreadyCohortCount: number
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

// Sumber: App\Data\View\Period\ReadinessCheckData — BR-FE-36. `blocking` = dibutuhkan sekarang (G4-W8).
export interface ReadinessCheck {
    check: 'missing_date' | 'date_order' | 'required_evaluation_form'
    title: string
    issues: { message: string; blocking: boolean }[]
    state: 'passed' | 'warning' | 'failed'
}

// Sumber: App\Data\View\Period\CohortReadinessData — BR-FE-36.
export interface CohortReadiness {
    periodId: number
    phaseId: number
    phaseTitle: string
    versionLabel: string | null
    running: boolean
    status: 'ready' | 'pending' | 'blocked' | 'needs_fix'
    checks: ReadinessCheck[]
}

// Sumber: FormEngine\CohortReadinessController::index.
export interface PeriodReadinessPageProps {
    period: { id: number; name: string }
    cohorts: CohortReadiness[]
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
    // BR-FE-32 — langkah tahap terbit yang memakai aturan akses Form ini; > 0 menghalangi hapus.
    stepCount: number
    isActive: boolean
    // BR-FE-34 — false = lencana "Belum terbit".
    isPublished: boolean
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
// `id` null = field baru di draf (BR-FE-34).
export interface FormFieldEditData {
    id: number | null
    label: string
    fieldTypeId: number
    isRequired: boolean
    requiredSince: string | null
    answerCount: number
    options: { id: number | null; label: string }[]
}

// Sumber: App\Queries\Form\FormPublishImpact — dampak Terbitkan Form (BR-FE-34).
export interface FormPublishImpact {
    newRequired: number
    draftOwners: number
    relabelled: { label: string; answered: number }[]
    removed: number
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
    hasDraft: boolean
    publishedAt: string | null
    // Isi draf bila ada, kalau tidak field terbit; `publishedFields` pembanding peringatan label (G3-W9).
    fields: FormFieldEditData[]
    publishedFields: FormFieldEditData[]
    archivedFields: ArchivedFieldData[]
    impact: FormPublishImpact
}

// Sumber: App\Data\View\FormEngine\FormAccessRuleData — `phaseCount` > 0 mengunci hapus (BR-FE-20).
export interface FormAccessRuleData {
    id: number
    permission: string
    permissionLabel: string
    organizationLabel: string
    phaseCount: number
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
// `versionLabel` null = belum pernah terbit; `hasDraft` = struktur belum terbit (BR-FE-33).
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
    versionLabel: string | null
    hasDraft: boolean
}

// Sumber: FormEngine\FormPhaseController::index — pustaka fase; `filterForm` dari `?form=`.
export interface PhaseIndexPageProps {
    phases: PhaseCardData[]
    filterForm: FormHeaderData | null
    can: { linkPeriod: boolean }
}

export type FormEditPageProps = FormEditGeneralProps | FormEditAccessProps

// Sumber: App\Data\View\FormEngine\PhaseDetailEditData — langkah draf atau versi terbaru (BR-FE-33);
// tanpa id dan hitungan pakai, karena versi terbit beku (BR-FE-32).
export interface PhaseDetailEditData {
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
    evaluationForms: StepEvaluationFormData[]
}

export interface PhaseVersionData {
    label: string
    publishedAt: string
    publishedBy: string | null
    // BR-FE-32 — angkatan yang terpaku pada versi ini.
    cohortCount: number
}

// Sumber: App\Data\View\FormEngine\PhaseEditData — `id` null = mode buat (W94).
export interface PhaseEditData {
    id: number | null
    title: string
    description: string | null
    isActive: boolean
    // BR-FE-33 — draf bila ada, selain itu versi terbaru.
    steps: PhaseDetailEditData[]
    hasDraft: boolean
    // BR-FE-32 — "Versi n" terbaru; null = belum pernah terbit.
    versionLabel: string | null
    versions: PhaseVersionData[]
    impact: { nextLabel: string; pinnedCohorts: number }
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

// Sumber: App\Data\View\FormEngine\StepEvaluationFormData — pemasangan di langkah, bagian isi draf
// (BR-FE-30, BR-FE-33); `title` kosong = form-nya sudah dihapus dari pustaka.
export interface StepEvaluationFormData {
    formId: number
    title: string
    versionLabel: string
    isRequired: boolean
    isActive: boolean
}

// Sumber: App\Queries\Review\PublishedEvaluationFormOptions — `value` id Form Evaluasi terbit.
export interface EvaluationOption extends FilterOption {
    versionLabel: string
}

// Sumber: FormEngine\FormPhaseController::edit/create.
export interface PhaseEditPageProps {
    phase: PhaseEditData
    accessOptions: PhaseAccessOption[]
    phaseTypeOptions: FilterOption[]
    // BR-FE-30 — Form Evaluasi terbit, urut judul; tiap langkah menyaring yang sudah terpasang.
    evaluationOptions: EvaluationOption[]
    // B5 — id Form tapis pustaka asal; Kembali/Batalkan/simpan kembali ke pustaka bertapis itu.
    returnForm: number | null
}
