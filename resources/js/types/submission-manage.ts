// Props layar pengajuan sisi Operator (OPERATOR-02-*), cermin Data PHP di
// app/Data/View/Submission/Manage.
import type { Paginated } from '@/types/pagination'
import type { FilterOption, ReviewerScore, SubmissionShowPageProps } from '@/types/submission-page'
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
}
