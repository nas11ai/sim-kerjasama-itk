// Kontrak props halaman Submission/*, mengikuti app/Data/View/Submission dan
// app/Queries/Submission/Researcher*.
import type { Paginated } from '@/types/pagination'
import type { SubmissionStatusView } from '@/types/submission-status'

export interface FilterOption {
    value: string
    label: string
}

// Satu dropdown filter di FilterToolbar; `key` = nama parameter query.
export interface FilterSelectSpec<K extends string = string> {
    key: K
    label: string
    allLabel: string
    options: FilterOption[]
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
    canCreate: boolean
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
    manageTargetOutput: boolean
    discussReview: boolean
}

export interface SubmissionAnswer {
    label: string
    value: string | null
    isFile: boolean
    downloadUrl: string | null
}

export interface SubmissionMember {
    name: string
    roleLabel: string
}

export interface SubmissionTargetOutput {
    id: number
    type: string
    description: string | null
}

export interface BudgetItem {
    id: number
    componentId: number
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

// Sumber: TargetOutputChangeData. `from`/`to` berupa nama tipe.
export interface TargetOutputChange {
    description: string
    actor: string | null
    at: string | null
    from: string | null
    to: string | null
}

// Sumber: SubmissionDecisionData — BR-REV-05, BR-REV-07; `reason` hanya pada penolakan.
export interface SubmissionDecision {
    outcome: 'approved' | 'rejected'
    reason: string | null
    decidedBy: string | null
    decidedAt: string | null
}

// Sumber: SubmittedEvaluationSheets (@phpstan-type Score).
export interface ReviewerScore {
    reviewer: string
    reviewer_type: string
    submitted_at: string | null
    // Lembar milik Reviewer yang digantikan; hanya sampai ke layar Operator (W22).
    replaced: boolean
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
    targetOutputTypes: FilterOption[]
    targetHistory: TargetOutputChange[]
    budget: SubmissionBudget
    history: SubmissionHistoryEntry[]
    decision: SubmissionDecision | null
    scores: ReviewerScores
    phaseAccess: PhaseAccess
    printableDocuments: FilterOption[]
    can: SubmissionAbilities
}
