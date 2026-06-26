import type {
    FormPhaseFaculty,
    FormPhaseProdi,
    FormPhaseStatus,
    FormPhaseTotal,
    FormPhaseByPeriod,
    RecentSubmission,
    TotalSubmission,
    TotalByStatus,
    TotalByFaculty,
    TotalByProdi,
    UserRecent,
    ReviewerRecent,
    EvaluationStatus,
    ReviewerByYear,
    ReviewerByFaculty,
    ReviewerByProdi,
    ReviewerActiveStatus,
} from './api'

export interface TotalByRoleItem {
    id: number
    total_reviewers: number
    reviewer_role_name: string
}

export interface FormPhaseStats {
    formPhaseFaculty: FormPhaseFaculty[]
    formPhaseProdi: FormPhaseProdi[]
    formPhaseStatus: FormPhaseStatus[]
    formPhaseTotal: FormPhaseTotal[]
    formPhaseByPeriod: FormPhaseByPeriod[]
}

export interface FormSubmissionStats {
    recentSubmissions: RecentSubmission[]
    totalSubmissions: TotalSubmission[]
    totalByStatus: TotalByStatus[]
    totalByFaculty: TotalByFaculty[]
    totalByProdi: TotalByProdi[]
}

export interface UserStats {
    userRecent: UserRecent[]
    totalUsers: number
    totalAdmin: number
    totalNonAdmin: number
    totalProdi: number
    totalFaculty: number
}

export interface SubmissionReviewerStats {
    reviewerRecent: ReviewerRecent[]
    totalReviewers: number
    totalByRole: TotalByRoleItem[]
    evaluationStatus: EvaluationStatus[]
    reviewerByYear: ReviewerByYear[]
    reviewerByFaculty: ReviewerByFaculty[]
    reviewerByProdi: ReviewerByProdi[]
    reviewerActiveStatus: ReviewerActiveStatus[]
}
