// Props layar pengajuan sisi Operator (OPERATOR-02-*), cermin Data PHP di
// app/Data/View/Submission/Manage.
import type { Paginated } from '@/types/pagination'
import type { OutputRow, OutputWriteAccess } from '@/types/research-output'
import type {
    FilterOption,
    PhaseAccess,
    ReviewerScore,
    SubmissionShowPageProps,
} from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'
import type { ReviewThread } from '@/types/submission-revision'

export interface OperatorSubmissionRow {
    id: number
    title: string
    leadName: string
    organization: string | null
    scheme: string | null
    status: SubmissionStatusView
    submittedAt: string | null
    reviewerCount: number
}

export type ManageIndexFilters = {
    search: string
    status: string
    period: string
    scheme: string
}

export interface ManageIndexPageProps {
    submissions: Paginated<OperatorSubmissionRow>
    filters: ManageIndexFilters
    statusOptions: FilterOption[]
    periodOptions: FilterOption[]
    schemeOptions: FilterOption[]
}

// Sumber: OperatorAbilitiesData — Policy yang sama dengan endpoint-nya (ADR 0015).
export interface OperatorAbilities {
    assignReviewers: boolean
    replaceReviewer: boolean
    grantOverride: boolean
    transferOwnership: boolean
    reject: boolean
    withdraw: boolean
}

// Sumber: ManagedReviewerData. `type` mentah: 'internal' | 'external'.
export interface ManagedReviewer {
    reviewerId: number
    name: string
    nidn: string | null
    type: string
    replaced: boolean
    evaluationLabel: string
    // BR-IAM-05 — false = akun reviewer dinonaktifkan; penugasan tetap, perlu diganti.
    accountActive: boolean
}

// Sumber: OwnershipCandidateData.
export interface OwnershipCandidate {
    userId: number
    name: string
    organization: string | null
}

// Sumber: OverrideTargetData.
export interface OverrideTarget {
    phaseLabel: string | null
    affectedReviewers: string[]
}

// Sumber: ReviewerCandidateData — `blockedReason` = penugasan pasti ditolak (BR-REV-02/03).
export interface ReviewerCandidate {
    reviewerId: number
    name: string
    nidn: string | null
    type: string
    activeLoad: number
    overloaded: boolean
    blockedReason: string | null
}

export type OperatorActionKey = 'assign' | 'extend' | 'transfer' | 'reject' | 'withdraw'

// Sumber: OperatorSubmissionDetail — detail peneliti tanpa pilihan target luaran.
export interface ManageShowPageProps extends Omit<SubmissionShowPageProps, 'targetOutputTypes'> {
    organization: string | null
    reviewers: ManagedReviewer[]
    activeReviewerCount: number
    minimumReviewers: number
    needsReview: boolean
    evaluationSheets: ReviewerScore[]
    threads: ReviewThread[]
    ownershipCandidates: OwnershipCandidate[]
    overrideTarget: OverrideTarget
    operatorCan: OperatorAbilities
    // W144 — tab "Luaran"; null kecuali APPROVED/WITHDRAWN. Fase null = tanpa detail Upload Luaran.
    outputs: OutputRow[] | null
    outputAccess: OutputWriteAccess | null
    outputPhaseAccess: PhaseAccess | null
    outputOverrideTarget: OverrideTarget | null
    // Inertia::optional — hanya ada setelah partial reload dialog penugasan.
    reviewerCandidates?: ReviewerCandidate[]
}
