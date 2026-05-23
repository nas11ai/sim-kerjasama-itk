export type SubmissionStatusValue =
    | 'pending'
    | 'under_review'
    | 'needs_revision'
    | 'approved'
    | 'rejected'

export interface SubmissionStatusOption {
    value: SubmissionStatusValue
    label: string
    color: string
}
