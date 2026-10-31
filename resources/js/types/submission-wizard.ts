// Kontrak props Submission/WizardPage, mengikuti app/Data/View/Submission/Wizard dan
// app/Queries/Submission/SubmissionWizard + ResearcherStartOptions.
import type {
    BudgetItem,
    FilterOption,
    SubmissionAbilities,
    SubmissionTargetOutput,
} from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'

// Nama di database/seeders/FieldTypeSeeder.php.
export type WizardFieldType =
    | 'text'
    | 'email'
    | 'number'
    | 'textarea'
    | 'select'
    | 'radio'
    | 'checkbox'
    | 'date'
    | 'time'
    | 'file'
    | 'url'
    | 'phone'
    | 'scheme_selector'
    | 'trl_selector'

// Sumber: WizardStepData.
export interface WizardStep {
    number: number
    label: string
    completed: boolean
    blockedReason: string | null
}

// Sumber: WizardSubmissionData.
export interface WizardSubmission {
    id: number
    title: string
    status: SubmissionStatusView
    period: string | null
    form: string
    lastUpdatedAt: string
}

// Sumber: WizardFieldData. Berkas tidak membawa `value`, hanya nama dan tautannya.
export interface WizardField {
    id: number
    label: string
    type: WizardFieldType
    required: boolean
    value: string | null
    options: FilterOption[]
    fileName: string | null
    downloadUrl: string | null
}

// Isian satu field di klien: teks, berkas baru, atau kosong.
export type WizardFieldValue = string | File | null

// Sumber: WizardMissingFieldData.
export interface WizardMissingField {
    fieldId: number
    label: string
    message: string
    step: number
}

// Sumber: WizardReviewData.
export interface WizardReview {
    scheme: string | null
    trl: string | null
    memberCount: number
    targetOutputCount: number
    budgetTotal: number
    maxBudget: number | null
    fileNames: string[]
    missingFields: WizardMissingField[]
    accountBlock: string | null
}

// Sumber: WizardMemberRowData. Ketua ber-`id` 0 dan tidak pernah `removable`.
export interface WizardMemberRow {
    id: number
    name: string
    role: string
    roleLabel: string
    unit: string | null
    nidn: string | null
    removable: boolean
}

// Sumber: WizardStudentRowData.
export interface WizardStudentRow {
    id: number
    name: string
    studentId: string
    studyProgram: string | null
}

// Sumber: WizardPartnerRowData.
export interface WizardPartnerRow {
    id: number
    institutionName: string
    contactPerson: string | null
}

// Sumber: WizardMembersData. Batas null = skema tidak membatasi.
export interface WizardMembers {
    members: WizardMemberRow[]
    students: WizardStudentRow[]
    partners: WizardPartnerRow[]
    memberLimit: number | null
    studentLimit: number | null
    roleOptions: FilterOption[]
}

// Sumber: MemberCandidateData.
export interface MemberCandidate {
    id: number
    name: string
    nidn: string | null
    blockedReason: string | null
}

// Sumber: WizardBudgetData.
export interface WizardBudget {
    items: BudgetItem[]
    total: number
    maxBudget: number | null
    components: FilterOption[]
    blockedReason: string | null
    editable: boolean
}

// Sumber: StartFormOptionData.
export interface StartFormOption {
    formId: number
    formPhaseId: number
    title: string
    draftId: number | null
}

// Sumber: StartPeriodOptionData.
export interface StartPeriodOption {
    id: number
    name: string
    closesAt: string | null
    forms: StartFormOption[]
}

export interface SubmissionWizardPageProps {
    step: number
    steps: WizardStep[]
    submission: WizardSubmission | null
    startOptions: StartPeriodOption[]
    fields: WizardField[]
    targetOutputs: SubmissionTargetOutput[]
    targetOutputTypes: FilterOption[]
    review: WizardReview | null
    members: WizardMembers | null
    budget: WizardBudget | null
    // Hanya ada setelah partial reload pencarian anggota langkah 4.
    memberCandidates?: MemberCandidate[]
    can: SubmissionAbilities | null
}
