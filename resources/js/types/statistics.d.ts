export interface FormPhaseTotalItem {
    total_forms: number
    total_submissions: number
}

export interface FormPhaseByPeriodItem {
    submission_period_id: number
    submission_period_name: string
    total_submissions: number
}

export interface TotalByStatusItem {
    status: string
    total: number
}

export interface RecentSubmissionItem {
    id: number
    name: string
    total_submissions: number
}

export interface TotalByRoleItem {
    id: number
    total_reviewers: number
    reviewer_role_name: string
}

export interface FormPhaseStats {
    formPhaseFaculty: Record<string, unknown>[]
    formPhaseProdi: Record<string, unknown>[]
    formPhaseStatus: Record<string, unknown>[]
    formPhaseTotal: FormPhaseTotalItem[]
    formPhaseByPeriod: FormPhaseByPeriodItem[]
}

export interface FormSubmissionStats {
    recentSubmissions: RecentSubmissionItem[]
    totalSubmissions: Record<string, unknown>[]
    totalByStatus: TotalByStatusItem[]
    totalByFaculty: Record<string, unknown>[]
    totalByProdi: Record<string, unknown>[]
}

export interface UserStats {
    userRecent: Record<string, unknown>[]
    totalUsers: number
    totalAdmin: number
    totalNonAdmin: number
    totalProdi: number
    totalFaculty: number
}

export interface SubmissionReviewerStats {
    reviewerRecent: Record<string, unknown>[]
    totalReviewers: number
    totalByRole: TotalByRoleItem[]
    evaluationStatus: Record<string, unknown>[]
    reviewerByYear: Record<string, unknown>[]
    reviewerByFaculty: Record<string, unknown>[]
    reviewerByProdi: Record<string, unknown>[]
    reviewerActiveStatus: Record<string, unknown>[]
}
