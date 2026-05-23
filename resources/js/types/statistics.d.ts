export interface FormPhaseStats {
    formPhaseFaculty: any[]
    formPhaseProdi: any[]
    formPhaseStatus: any[]
    formPhaseTotal: any[]
    formPhaseByPeriod: any[]
}

export interface FormSubmissionStats {
    recentSubmissions: any[]
    totalSubmissions: any[]
    totalByStatus: any[]
    totalByFaculty: any[]
    totalByProdi: any[]
}

export interface UserStats {
    userRecent: any[]
    totalUsers: number
    totalAdmin: number
    totalNonAdmin: number
    totalProdi: number
    totalFaculty: number
}

export interface SubmissionReviewerStats {
    reviewerRecent: any[]
    totalReviewers: number
    totalByRole: any[]
    evaluationStatus: any[]
    reviewerByYear: any[]
    reviewerByFaculty: any[]
    reviewerByProdi: any[]
    reviewerActiveStatus: any[]
}
