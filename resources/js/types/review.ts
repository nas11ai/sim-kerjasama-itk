// Kontrak props halaman Review/*, mengikuti app/Data/View/Review dan app/Queries/Review/Reviewer*.
import type { SubmissionAnswer } from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'
import type { WizardField } from '@/types/submission-wizard'

// Sumber: EvaluationSubmissionData.
export interface EvaluationSubmission {
    id: number
    title: string
    scheme: string | null
    period: string | null
    leadName: string
    memberCount: number
    status: SubmissionStatusView
}

// `state` hanya memilih tampilan; boleh-tidaknya menyunting dibaca dari `canEdit`.
export type EvaluationFormState = 'not_started' | 'draft' | 'submitted' | 'locked' | 'overdue'

// Sumber: EvaluationFormData. `assignmentId` null = form belum dimulai.
export interface EvaluationForm {
    formId: number
    assignmentId: number | null
    title: string
    description: string | null
    required: boolean
    state: EvaluationFormState
    dueDate: string | null
    submittedAt: string | null
    finalNotes: string | null
    canEdit: boolean
    fields: WizardField[]
}

export interface EvaluationPageProps {
    submission: EvaluationSubmission
    answers: SubmissionAnswer[]
    forms: EvaluationForm[]
    activeFormId: number | null
    evaluationSettled: boolean
}
