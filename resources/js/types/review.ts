// Kontrak props halaman Review/*, mengikuti app/Data/View/Review dan app/Queries/Review/Reviewer*.
import type { Paginated } from '@/types/pagination'
import type { FilterOption, ReviewerScores, SubmissionAnswer } from '@/types/submission-page'
import type { ReviewThread } from '@/types/submission-revision'
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

// Sumber: AssignmentCardData. `state` memilih aksi kartu.
export interface AssignmentCard {
    submissionId: number
    title: string
    leadName: string
    scheme: string | null
    dueDate: string | null
    state: 'evaluation_pending' | 'discussion_open' | 'done'
    stateLabel: string
}

export interface AssignmentIndexPageProps {
    needsAction: AssignmentCard[]
    done: AssignmentCard[]
    filter: 'action' | 'all'
}

export interface EvaluationPageProps {
    submission: EvaluationSubmission
    answers: SubmissionAnswer[]
    forms: EvaluationForm[]
    activeFormId: number | null
    evaluationSettled: boolean
}

// Sumber: ReviewRoundProgressData. Hanya hitungan, tanpa nama reviewer lain (BR-REV-11).
export interface ReviewRoundProgress {
    kind: ReviewRoundKind
    number: number
    finished: number
    total: number
}

export type ReviewRoundKind = 'assessment' | 'recheck'

// Sumber: ReviewerRoundData — putaran berjalan dari sisi reviewer ini (BR-REV-16).
export interface ReviewerRound {
    kind: ReviewRoundKind
    finishLabel: string
    isParticipant: boolean
    finished: boolean
    canFinish: boolean
    reason: string | null
    progress: ReviewRoundProgress
}

export interface SummaryPageProps {
    submission: EvaluationSubmission
    threads: ReviewThread[]
    canCreateThread: boolean
    scores: ReviewerScores
    // null = tidak ada putaran berjalan.
    round: ReviewerRound | null
}

// Sumber: ReviewHistoryRowData. `summaryUrl` null = penugasan digantikan (BR-REV-10).
export interface ReviewHistoryRow {
    id: number
    title: string
    scheme: string | null
    context: string | null
    completedAt: string | null
    status: 'completed' | 'pending' | 'replaced'
    statusLabel: string
    summaryUrl: string | null
}

export interface HistoryPageProps {
    history: Paginated<ReviewHistoryRow>
    filters: { search: string; year: string }
    yearOptions: FilterOption[]
}
