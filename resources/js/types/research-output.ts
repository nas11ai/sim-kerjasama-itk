import type { SubmissionStatusView } from '@/types/submission-status'

// Cermin App\Data\View\ResearchOutput\OutputWriteAccessData — dihitung backend (W146).
export interface OutputWriteAccess {
    canWrite: boolean
    blockedReason: string | null
    deadline: string | null
    overrideUntil: string | null
}

export interface OutputRow {
    id: number
    typeName: string
    headline: string
    meta: string | null
    fileCount: number
}

export interface OutputSubmissionGroup {
    submissionId: number
    title: string
    scheme: string | null
    status: SubmissionStatusView
    access: OutputWriteAccess
    outputs: OutputRow[]
}

export interface ResearchOutputIndexPageProps {
    groups: OutputSubmissionGroup[]
}
