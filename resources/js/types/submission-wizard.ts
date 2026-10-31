// Kontrak props Submission/WizardPage, mengikuti app/Data/View/Submission/Wizard dan
// app/Queries/Submission/SubmissionWizard + ResearcherStartOptions.
import type {
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
    can: SubmissionAbilities | null
}
