import type { SubmissionStatusView } from '@/types/submission-status'

// Cermin App\Enums\MonevCycleState — keadaan dihitung backend (MonevCycles), bukan di sini.
export type MonevCycleState = 'done' | 'submitted' | 'open' | 'closed' | 'locked' | 'frozen'

export interface MonevCycle {
    detailId: number
    name: string
    order: number
    needsReview: boolean
    deadline: string | null
    state: MonevCycleState
    blockedReason: string | null
    reportId: number | null
    submittedAt: string | null
    openedAt: string | null
}

export interface MonevSubmission {
    id: number
    title: string
    scheme: string | null
    status: SubmissionStatusView
    canFill: boolean
    cycles: MonevCycle[]
}

export interface MonevIndexPageProps {
    submissions: MonevSubmission[]
}

export type PhaseNodeState = 'done' | 'active' | 'locked' | 'waiting'

export interface PhaseNode {
    label: string
    meta: string
    state: PhaseNodeState
}
