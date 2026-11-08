// Kontrak props Submission/RevisionPage, mengikuti app/Data/View/Submission/Revision dan
// app/Queries/Submission/SubmissionRevision.
import type { SubmissionAbilities } from '@/types/submission-page'
import type { SubmissionStatusView } from '@/types/submission-status'
import type { WizardMissingField } from '@/types/submission-wizard'

// Sumber: RevisionSubmissionData.
export interface RevisionSubmission {
    id: number
    title: string
    status: SubmissionStatusView
}

// Sumber: ReviewAttachmentData.
export interface ReviewAttachment {
    name: string
    url: string
}

// Sumber: ReviewCommentData.
export interface ReviewComment {
    id: number
    author: string
    body: string
    at: string | null
    replies: ReviewComment[]
    attachments: ReviewAttachment[]
}

// Sumber: ReviewThreadData. `reviewer` adalah penulis asli ulir.
export interface ReviewThread {
    id: number
    // Penulis asli ulir (BR-REV-15).
    reviewer: string
    // Pemegang sekarang bila ulir diwarisi reviewer pengganti; null bila tidak.
    inheritedBy: string | null
    isOpen: boolean
    notes: string | null
    at: string | null
    attachments: ReviewAttachment[]
    comments: ReviewComment[]
}

export interface SubmissionRevisionPageProps {
    submission: RevisionSubmission
    threads: ReviewThread[]
    missingFields: WizardMissingField[]
    accountBlock: string | null
    can: SubmissionAbilities
}
