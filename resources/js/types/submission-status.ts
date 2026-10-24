import type { BadgeVariant } from '@/types/BadgeVariant'

export type SubmissionStatusKey =
    | 'draft'
    | 'submitted'
    | 'under_review'
    | 'needs_revision'
    | 'resubmitted'
    | 'approved'
    | 'rejected'
    | 'withdrawn'

export interface SubmissionStatusView {
    key: SubmissionStatusKey
    label: string
    variant: BadgeVariant
}
