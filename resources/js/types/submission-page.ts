// Kontrak props halaman Submission/*, mengikuti app/Data/View/Submission dan
// app/Queries/Submission/Researcher*.
import type { Paginated } from '@/types/pagination'
import type { SubmissionStatusView } from '@/types/submission-status'

export interface FilterOption {
    value: string
    label: string
}

// Sumber: SubmissionRowData.
export interface SubmissionRow {
    id: number
    title: string
    scheme: string | null
    period: string | null
    status: SubmissionStatusView
    submittedAt: string | null
    isLead: boolean
    isArchived: boolean
}

export type SubmissionIndexFilters = {
    search: string
    status: string
    period: string
}

export interface SubmissionIndexPageProps {
    submissions: Paginated<SubmissionRow>
    filters: SubmissionIndexFilters
    statusOptions: FilterOption[]
    periodOptions: FilterOption[]
}

// Sumber: SubmissionDetailData. `viewerRole` hanya untuk label; tombol membaca `can`.
export interface SubmissionDetail {
    id: number
    title: string
    scheme: string | null
    period: string | null
    trl: string | null
    leadName: string
    status: SubmissionStatusView
    submittedAt: string | null
    isArchived: boolean
    viewerRole: 'lead_researcher' | 'co_investigator' | 'member' | 'viewer'
    viewerRoleLabel: string
    editUrl: string | null
}

// Sumber: SubmissionAbilitiesData.
export interface SubmissionAbilities {
    view: boolean
    update: boolean
    submit: boolean
    resubmit: boolean
    withdraw: boolean
}

export interface SubmissionAnswer {
    label: string
    value: string | null
    isFile: boolean
}

export interface SubmissionMember {
    name: string
    roleLabel: string
}

export interface SubmissionTargetOutput {
    type: string
    description: string | null
}

export interface BudgetItem {
    component: string
    item: string
    volume: number
    unit: string
    unitPrice: number
    total: number
}

export interface SubmissionBudget {
    items: BudgetItem[]
    total: number
    editable: boolean
    lockReason: 'terminal' | 'read_only' | null
}

export interface SubmissionHistoryEntry {
    description: string
    actor: string | null
    at: string | null
}

export interface SubmissionDecision {
    reason: string | null
    decidedBy: string | null
    decidedAt: string | null
}

// Sumber: VisibleReviewerScores (@phpstan-type Score).
export interface ReviewerScore {
    reviewer: string
    reviewer_type: string
    submitted_at: string | null
    answers: { label: string; value: string | null }[]
}

export interface ReviewerScores {
    visible: boolean
    reason: string | null
    scores: ReviewerScore[]
}

// Sumber: PhaseAccessGate::presentFor.
export interface PhaseAccess {
    is_open: boolean
    deadline: string | null
    form_phase_detail_id: number | null
    override: {
        id: number
        status: 'active' | 'expired'
        expires_at: string | null
        reason: string | null
    } | null
}

export interface SubmissionShowPageProps {
    submission: SubmissionDetail
    answers: SubmissionAnswer[]
    members: SubmissionMember[]
    targetOutputs: SubmissionTargetOutput[]
    budget: SubmissionBudget
    history: SubmissionHistoryEntry[]
    decision: SubmissionDecision | null
    scores: ReviewerScores
    phaseAccess: PhaseAccess
    printableDocuments: FilterOption[]
    can: SubmissionAbilities
}
